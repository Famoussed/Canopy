# 12 — File Management

MinIO entegrasyonu, dosya yükleme/indirme akışı, kısıtlamalar ve güvenlik.

**İlişkili Dokümanlar:** [Domain Model](./02-DOMAIN_MODEL.md) | [API Design](./07-API_DESIGN.md) | [Infrastructure](./09-INFRASTRUCTURE.md)

---

## 1. Genel Bakış

Dosya yönetimi **MinIO** (S3-uyumlu object storage) üzerinden Laravel Filesystem S3 driver'ı ile gerçekleşir.

Dosyalar **User Story**, **Task** ve **Issue** entity'lerine Polymorphic ilişki ile bağlanır (`Attachment` modeli).

---

## 2. Mimari

```
Client (Livewire)
    │
    ├── Upload: multipart/form-data
    │       │
    │   Controller → AttachmentService → UploadFileAction
    │                                        │
    │                                   Storage::disk('s3')->put()
    │                                        │
    │                                      MinIO
    │
    └── Download: Signed URL
            │
        Controller → AttachmentService → Storage::temporaryUrl()
                                              │
                                            MinIO
```

---

## 3. Kısıtlamalar

| Kural | Değer |
|-------|-------|
| Max dosya boyutu | 20 MB |
| İzin verilen tipler | `jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, txt, md, zip` |
| Entity başına max dosya | 10 |
| Dosya adı max uzunluk | 255 karakter |
| Toplam proje depolama | MVP'de limit yok (ileriye dönük eklenebilir) |

---

## 4. Attachment Model

```php
// app/Models/Attachment.php
class Attachment extends Model
{
    use HasUuids;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'uploaded_by',
        'original_name',
        'stored_path',
        'mime_type',
        'size',
    ];

    // ─── Relations ───
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ─── Scopes ───
    public function scopeForEntity(Builder $query, string $type, string $id): Builder
    {
        return $query->where('attachable_type', $type)
                     ->where('attachable_id', $id);
    }

    // ─── Accessors ───
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
```

---

## 5. Action Katmanı

### 5.1 UploadFileAction

```php
// app/Actions/File/UploadFileAction.php
class UploadFileAction
{
    /**
     * Dosyayı MinIO'ya yükler ve Attachment kaydı oluşturur.
     */
    public function execute(
        UploadedFile $file,
        Model $attachable,
        User $uploader,
    ): Attachment {
        // Depolama yolu: {entity_type}/{entity_id}/{uuid}_{filename}
        $entityType = class_basename($attachable);
        $path = sprintf(
            '%s/%s/%s_%s',
            Str::lower($entityType),
            $attachable->id,
            Str::orderedUuid(),
            Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            . '.' . $file->getClientOriginalExtension()
        );

        // MinIO'ya yükle
        Storage::disk('s3')->put($path, $file->getContent());

        // DB kaydı
        return Attachment::create([
            'attachable_type' => get_class($attachable),
            'attachable_id'   => $attachable->id,
            'uploaded_by'     => $uploader->id,
            'original_name'   => $file->getClientOriginalName(),
            'stored_path'     => $path,
            'mime_type'       => $file->getMimeType(),
            'size'            => $file->getSize(),
        ]);
    }
}
```

### 5.2 DeleteFileAction

```php
// app/Actions/File/DeleteFileAction.php
class DeleteFileAction
{
    /**
     * MinIO'dan dosyayı siler ve DB kaydını kaldırır.
     */
    public function execute(Attachment $attachment): void
    {
        // MinIO'dan sil
        Storage::disk('s3')->delete($attachment->stored_path);

        // DB'den sil
        $attachment->delete();
    }
}
```

---

## 6. Service Katmanı

```php
// app/Services/AttachmentService.php
class AttachmentService
{
    public function __construct(
        private UploadFileAction $uploadAction,
        private DeleteFileAction $deleteAction,
    ) {}

    public function upload(
        UploadedFile $file,
        Model $attachable,
        User $uploader,
    ): Attachment {
        // İş kuralı: Entity başına max 10 dosya
        $currentCount = $attachable->attachments()->count();
        if ($currentCount >= 10) {
            throw new \DomainException('Maximum 10 attachments per entity.');
        }

        return DB::transaction(function () use ($file, $attachable, $uploader) {
            return $this->uploadAction->execute($file, $attachable, $uploader);
        });
    }

    public function delete(Attachment $attachment): void
    {
        DB::transaction(function () use ($attachment) {
            $this->deleteAction->execute($attachment);
        });
    }

    /**
     * Geçici indirme URL'i oluşturur (15 dk geçerli).
     */
    public function getDownloadUrl(Attachment $attachment): string
    {
        return Storage::disk('s3')->temporaryUrl(
            $attachment->stored_path,
            now()->addMinutes(15)
        );
    }

    /**
     * Entity'nin tüm dosyalarını listeler.
     */
    public function listFor(Model $attachable): Collection
    {
        return $attachable->attachments()
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get();
    }
}
```

---

## 7. FormRequest Validation

```php
// app/Http/Requests/Attachment/UploadAttachmentRequest.php
class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy'den kontrol
        return $this->user()->can('uploadAttachment', $this->route('entity'));
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480', // 20 MB in KB
                'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,md,zip',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max'   => 'File size cannot exceed 20 MB.',
            'file.mimes' => 'File type not allowed.',
        ];
    }
}
```

---

## 8. Laravel Filesystem Konfigürasyonu

```php
// config/filesystems.php
'disks' => [
    // ...
    's3' => [
        'driver'                  => 's3',
        'key'                     => env('AWS_ACCESS_KEY_ID'),
        'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
        'region'                  => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket'                  => env('AWS_BUCKET'),
        'url'                     => env('AWS_URL'),
        'endpoint'                => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
        'throw'                   => true,
    ],
],
```

---

## 9. MinIO Depolama Yapısı

```
taiga-files/                     (bucket)
├── userstory/
│   └── {story_id}/
│       ├── 01HQ..._requirements.pdf
│       └── 01HQ..._mockup.png
├── task/
│   └── {task_id}/
│       └── 01HQ..._screenshot.jpg
└── issue/
    └── {issue_id}/
        └── 01HQ..._error-log.txt
```

---

## 10. Güvenlik Kuralları

1. **Doğrudan erişim yok** — MinIO'ya public erişim kapalı. Tüm dosyalar signed URL ile sunulur.
2. **Policy kontrolü** — Yükleme ve silme işlemleri Policy ile denetlenir.
3. **MIME doğrulama** — Hem uzantı hem MIME type kontrol edilir.
4. **Path traversal koruması** — Dosya yolları UUID ile oluşturulur, kullanıcı girişi içermez.
5. **Signed URL süresi** — 15 dakika (yapılandırılabilir).

---

## 11. Polymorphic İlişki (Model Trait)

```php
// UserStory, Task, Issue modellerinde:
public function attachments(): MorphMany
{
    return $this->morphMany(Attachment::class, 'attachable');
}
```

Bu, `attachable_type` + `attachable_id` kombinasyonu ile herhangi bir entity'ye dosya bağlamayı sağlar.

---

**Önceki:** [11-NOTIFICATION_SYSTEM.md](./11-NOTIFICATION_SYSTEM.md)
**Sonraki:** [13-TESTING_STRATEGY.md](./13-TESTING_STRATEGY.md)
