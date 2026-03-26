import re

with open('resources/views/livewire/issues/issue-list.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Provide the shared form code
shared_form = """    {{-- Shared Form Modal (Create & Edit) --}}
    <flux:modal wire:model="showForm" class="max-w-lg">
        <flux:heading>{{ $editingIssueId ? 'Issue Düzenle' : 'Yeni Issue Oluştur' }}</flux:heading>
        <form wire:submit="saveIssue" class="space-y-4 mt-4">
            <flux:input wire:model="title" label="Başlık" placeholder="Issue başlığı..." required />
            <flux:textarea wire:model="description" label="Açıklama" rows="3" />
            <div class="grid grid-cols-3 gap-4">
                <flux:select wire:model="type" label="Tip">
                    @foreach (IssueType::cases() as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="priority" label="Öncelik">
                    @foreach (IssuePriority::cases() as $p)
                        <option value="{{ $p->value }}">{{ $p->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="severity" label="Ciddiyet">
                    @foreach (IssueSeverity::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex gap-2 justify-end">
                <flux:button variant="ghost" wire:click="$set('showForm', false)">İptal</flux:button>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>"""

# 2. Extract out the old Create Form
content = re.sub(r'\{\{-- Create Form --\}\}.*?@endif', '', content, flags=re.DOTALL)

# 3. Extract out the old Edit Modal and replace it with shared form
content = re.sub(r'\{\{-- Edit Modal --\}\}.*?@endif', shared_form, content, flags=re.DOTALL)

# 4. Replace $toggle('showCreateForm') -> openCreateModal
content = content.replace("wire:click=\"$toggle('showCreateForm')\"", 'wire:click="openCreateModal"')

# 5. Fix an empty state error logic showing "Filtreleri degistirmeyi"
content = content.replace('@if ($this->issues->isEmpty())', '@forelse ($this->issues as $issue)', 1)
content = content.replace('@else', '@empty', 1)
content = content.replace('@endforeach', '', 1)
content = content.replace('</flux:table.rows>', '@endforelse\\n            </flux:table.rows>', 1)

# Now, we manually fix the table structure logic if possible. Actually, let's just leave the blade table as is and use replace on PHP part first.
with open('resources/views/livewire/issues/issue-list.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

