# Test Senaryoları ve Dokümantasyonu

Bu dosya otomatik olarak test sınıflarındaki açıklama satırlarından (docblock) derlenmiştir.

## AnalyticsEndpointTest

F-15 & F-16: Burndown ve Velocity API endpoint testleri.

Analytics endpoint'lerinin doğru veri döndüğünü test eder.

Test Edilen Senaryolar:
 - **test_burndown_endpoint_returns_data**:
   Burndown endpoint returns data

 - **test_velocity_endpoint_returns_data**:
   Velocity endpoint returns data

 - **test_burndown_requires_authentication**:
   Burndown requires authentication

 - **test_non_member_cannot_access_burndown**:
   Non member cannot access burndown

---

## LoginTest

Kullanıcı Giriş (Login) Testi

Bu test sınıfı, kimlik doğrulama (authentication) sisteminin giriş,
kullanıcı bilgisi alma ve çıkış işlemlerinin doğru çalıştığını test eder.
Tüm testler /api/auth/* endpoint'lerine JSON istekleri göndererek çalışır.

Test Edilen Senaryolar:
 - test_user_can_login:
   Geçerli e-posta ve şifre ile POST /api/auth/login isteği yapılır.
   HTTP 200 dönmesi ve yanıtta id, name, email alanlarının bulunması beklenir.

 - test_login_fails_with_invalid_credentials:
   Yanlış e-posta veya şifre ile POST /api/auth/login isteği yapılır.
   HTTP 401 (Unauthorized) dönmesi beklenir.

 - test_user_can_get_own_info:
   Giriş yapmış kullanıcı olarak GET /api/auth/me isteği yapılır.
   HTTP 200 dönmesi ve yanıttaki id ile email alanlarının kullanıcıya
   ait olması beklenir.

 - test_user_can_logout:
   Giriş yapmış kullanıcı olarak POST /api/auth/logout isteği yapılır.
   HTTP 204 (No Content) dönmesi beklenir.

---

## RegisterTest

Kullanıcı Kayıt (Register) Testi

Bu test sınıfı, yeni kullanıcı kayıt işleminin doğru çalıştığını,
validasyon kurallarının uygulandığını ve benzersiz e-posta kontrolünün
yapıldığını test eder. Tüm istekler POST /api/auth/register endpoint'ine yapılır.

Test Edilen Senaryolar:
 - test_user_can_register:
   Geçerli name, email, password ve password_confirmation verileriyle
   kayıt isteği yapılır. HTTP 201 (Created) dönmesi, yanıtta id, name,
   email, created_at alanlarının bulunması ve veritabanında ilgili
   e-posta adresinin mevcut olması beklenir.

 - test_registration_requires_valid_data:
   Boş bir istek gönderilir. HTTP 422 (Unprocessable Entity) dönmesi
   ve name, email, password alanlarında validasyon hataları olması beklenir.

 - test_registration_requires_unique_email:
   Zaten kayıtlı olan bir e-posta adresiyle tekrar kayıt yapılmaya
   çalışılır. HTTP 422 dönmesi ve email alanında validasyon hatası
   olması beklenir. (Benzersiz e-posta kuralı.)

---

## AttachmentWorkflowTest

F-11 & F-12: Dosya yükleme ve silme testleri.

S3 disk fake'lenerek dosya yükleme ve silme iş akışını test eder.

Test Edilen Senaryolar:
 - **test_user_can_upload_file**:
   User can upload file

 - **test_upload_validates_required_fields**:
   Upload validates required fields

 - **test_upload_validates_file_size**:
   Upload validates file size

 - **test_user_can_delete_attachment**:
   User can delete attachment

 - **test_unauthenticated_user_cannot_upload**:
   Unauthenticated user cannot upload

---

## IssueWorkflowTest

Issue Workflow Testleri

Bu test sınıfı; Issue (Hata/Görev) kayıtlarının oluşturulması, güncellenmesi,
durum geçişleri (State Machine kuralları) ve filtreleme işlemlerinin
projenin iş kurallarına uygun şekilde çalıştığını doğrular.

Test Edilen Senaryolar:
- test_user_can_create_issue: Yeni bir issue oluşturulması ve varsayılanların kontrolü.
- test_issue_can_be_updated: Mevcut bir issue'nun güncellenmesi.
- test_issue_can_be_deleted: Issue'nun silinmesi.
- test_valid_status_transitions: Geçerli durum geçişleri (Open -> In Progress vb.).
- test_invalid_status_transition_throws_exception: Geçersiz durum geçişinde hata fırlatılması.

---

## NotificationWorkflowTest

F-13 & F-14: Bildirim oluşturma ve okundu işaretleme testleri.

Bildirim API endpoint'lerini ve NotificationService'i test eder.

Test Edilen Senaryolar:
 - **test_notification_can_be_created_via_service**:
   Notification can be created via service

 - **test_user_can_list_unread_notifications**:
   User can list unread notifications

 - **test_user_can_mark_notification_as_read**:
   User can mark notification as read

 - **test_user_can_mark_all_notifications_as_read**:
   User can mark all notifications as read

 - **test_read_notifications_not_shown_in_list**:
   Read notifications not shown in list

---

## MembershipWorkflowTest

Üyelik Workflow Testleri

Bu test sınıfı Project üzerindeki üye ekleme, çıkarma ve yetki süreçlerini doğrular.
ProjectService yalnızca create/update/delete sarmalar; üyelik işlemleri
AddMemberAction ve RemoveMemberAction üzerinden doğrudan test edilir.

Test Edilen Senaryolar:
- test_project_owner_can_add_member: Proje sahibinin yeni takım üyesi eklemesi.
- test_cannot_add_duplicate_member: Zaten takımda olan birinin tekrar eklenmesi durumunda fırlatılacak hata.
- test_member_role_can_be_updated: Üye rolünün güncellenmesi (membership modeli üzerinden).
- test_member_can_be_removed: Proje üyesinin projeden çıkarılması.

---

## ProjectTest

Proje CRUD ve Yetkilendirme Testi

Bu test sınıfı, proje oluşturma, listeleme, güncelleme ve silme (CRUD)
işlemlerini ve bu işlemler üzerindeki yetkilendirme kurallarını test eder.
Tüm istekler /api/projects endpoint'lerine JSON olarak yapılır.

Test Edilen Senaryolar:
 - test_user_can_create_project:
   Giriş yapmış kullanıcı yeni bir proje oluşturur. HTTP 201 dönmesi,
   proje adının doğru kaydedilmesi ve BR-13 iş kuralı gereği projeyi
   oluşturan kullanıcının otomatik olarak "Owner" rolüyle üye yapılması beklenir.

 - test_user_can_list_own_projects:
   Kullanıcının üyesi olduğu projeleri listelemesi test edilir.
   GET /api/projects isteğine HTTP 200 dönmesi ve yalnızca kullanıcıya
   ait 1 projenin listelenmesi beklenir.

 - test_owner_can_update_project:
   Proje sahibi (Owner) projenin adını günceller. HTTP 200 dönmesi
   ve güncellenmiş adın yanıtta yer alması beklenir.

 - test_owner_can_delete_project:
   Proje sahibi projeyi siler. HTTP 204 dönmesi ve projenin veritabanında
   soft-delete ile işaretlenmiş olması beklenir.

 - test_non_owner_cannot_delete_project:
   "Member" rolündeki bir kullanıcı projeyi silmeye çalışır.
   HTTP 403 (Forbidden) dönmesi beklenir. (Sadece Owner silebilir.)

---

## AdvancedRbacTest

P-08, P-09, P-10: Issue, Transfer Ownership, EnsureProjectMember testleri.

Üyelik kontrol middleware'i, issue yetkilendirme ve ownership transfer senaryoları.

Test Edilen Senaryolar:
 - **test_member_can_create_issue_with_priority**:
   Member can create issue with priority

 - **test_member_cannot_update_project**:
   Member cannot update project

 - **test_owner_can_update_project**:
   Owner can update project

 - **test_moderator_cannot_delete_project**:
   Moderator cannot delete project

 - **test_ensure_project_member_middleware_blocks_non_member**:
   Ensure project member middleware blocks non member

 - **test_ensure_project_member_middleware_allows_member**:
   Ensure project member middleware allows member

 - **test_ensure_project_member_middleware_allows_super_admin**:
   Ensure project member middleware allows super admin

 - **test_moderator_can_add_member**:
   Moderator can add member

 - **test_member_cannot_add_member**:
   Member cannot add member

 - **test_member_cannot_remove_other_member**:
   Member cannot remove other member

 - **test_max_member_limit_enforced_via_api**:
   Maksimum 5 üye limitine ulaşıldığında API üzerinden yeni üye eklenmesi engellenir (422).

---

## RbacTest

Rol Tabanlı Erişim Kontrolü (RBAC) Testi

Bu test sınıfı, proje içindeki farklı rollerin (Owner, Moderator, Member)
çeşitli işlemlere erişim yetkilerinin doğru uygulanıp uygulanmadığını test eder.
Her testten önce setUp() içinde bir proje ve 3 farklı rolde kullanıcı oluşturulur:
  - owner: Proje sahibi (Owner rolü)
  - moderator: Moderatör (Moderator rolü)
  - member: Üye (Member rolü)

Test Edilen Senaryolar:
 - test_member_cannot_create_story:
   "Member" rolündeki kullanıcı User Story oluşturmaya çalışır.
   HTTP 403 dönmesi beklenir. (Member story oluşturamaz.)

 - test_moderator_can_create_story:
   "Moderator" rolündeki kullanıcı User Story oluşturur.
   HTTP 201 dönmesi beklenir. (Moderator story oluşturabilir.)

 - test_member_can_create_issue:
   "Member" rolündeki kullanıcı Issue (bug report) oluşturur.
   HTTP 201 dönmesi beklenir. (Member issue oluşturabilir.)

 - test_non_member_cannot_access_project:
   Projeye üye olmayan bir dış kullanıcı proje içeriğine erişmeye çalışır.
   HTTP 403 dönmesi beklenir. (Proje üyesi olmayanlar erişemez.)

 - test_super_admin_can_access_any_project:
   Super Admin rolündeki kullanıcı, üyesi olmadığı bir projeye erişir.
   HTTP 200 dönmesi beklenir. (Super Admin her projeye erişebilir.)

 - test_owner_cannot_be_removed:
   Moderatör, proje sahibini (Owner) projeden çıkarmaya çalışır.
   Owner'ın project_memberships tablosunda hâlâ mevcut olması beklenir.
   (BR-14: Proje sahibi projeden çıkarılamaz.)

---

## TaskPolicyTest

P-05, P-06, P-07: Task RBAC policy testleri.

Task düzenle ve durum değiştirme yetkisi: task'ı oluşturan üye, Modüratör veya Owner.
Member kendi oluşturduğu task'ı düzenleyebilir ve durumunu değiştirebilir,
başkasının oluşturduğu task'ta bu yetkilere sahip değildir. (P19, P20)

Test Edilen Senaryolar:
 - **test_member_can_change_own_task_status**:
   Member kendi oluşturduğu task'ın durumunu değiştirebilir (created_by === user.id).

 - **test_member_cannot_change_others_task_status**:
   Member başkasının oluşturduğu task'ın durumunu değiştiremez.

 - **test_moderator_can_change_any_task_status**:
   Moderator can change any task status

 - **test_member_can_create_task**:
   Tüm proje üyeleri (Member dahil) task oluşturabilir (P17).

 - **test_moderator_can_create_task**:
   Moderator can create task

 - **test_non_member_cannot_create_task**:
   Proje üyesi olmayan kullanıcı task oluşturamaz.

 - **test_member_cannot_assign_task**:
   Member cannot assign task

 - **test_moderator_can_assign_task**:
   Moderator can assign task

 - **test_member_cannot_delete_task**:
   Member cannot delete task

 - **test_owner_can_delete_task**:
   Owner can delete task

 - **test_member_can_update_own_created_task**:
   Member kendi oluşturduğu task'ı düzenleyebilir (P20).

 - **test_member_cannot_update_task_created_by_others**:
   Member başkasının oluşturduğu task'ı düzenleyemez (P20).

---

## BacklogAndEpicWorkflowTest

F-17, F-18, F-19: Backlog sıralama, Epic completion, Scope change testleri.

Backlog reorder endpoint'i, epic tamamlanma yüzdesi ve sprint scope change algılama.

Test Edilen Senaryolar:
 - **test_backlog_stories_can_be_reordered**:
   Backlog stories can be reordered

 - **test_epic_completion_percentage_via_api**:
   Epic completion percentage via api

 - **test_story_move_to_sprint_triggers_scope_change**:
   Story move to sprint triggers scope change

 - **test_sprint_with_scope_changes_tracked**:
   Sprint with scope changes tracked

---

## EpicWorkflowTest

Epic Workflow Testleri

Bu test sınıfı Epic kayıtlarının temel CRUD işlemlerini doğrular.
Epic nesneleri proje hiyerarşisinin en üstünde yer aldığı için 
alt nesnelerin (UserStory) değişimlerinden de etkilenir.

Test Edilen Senaryolar:
- test_user_can_create_epic: Yeni Epic oluşturulması.
- test_user_can_update_epic: Mevcut Epic'in ayarlarının güncellenmesi.
- test_user_can_delete_epic: Epic silinmesi.

---

## SprintWorkflowTest

Sprint İş Akışı (Workflow) Testi

Bu test sınıfı, Sprint yaşam döngüsünün tamamını test eder: oluşturma,
başlatma, ikinci Sprint kısıtlaması ve Sprint kapatma sırasındaki
tamamlanmamış story'lerin backlog'a döndürülmesi.
Her testten önce setUp() içinde bir Owner kullanıcısı ve proje oluşturulur.

Test Edilen Senaryolar:
 - test_can_create_sprint:
   POST /api/projects/{slug}/sprints isteğiyle yeni Sprint oluşturulur.
   HTTP 201 dönmesi, Sprint adının doğru olması ve durumunun
   "planning" olması beklenir.

 - test_can_start_sprint:
   "Planning" durumundaki Sprint başlatılır (POST .../sprints/{id}/start).
   HTTP 200 dönmesi ve durumunun "active" olması beklenir.

 - test_cannot_start_second_sprint:
   Projede zaten aktif bir Sprint varken ikinci bir Sprint başlatılmaya
   çalışılır. HTTP 422 (Unprocessable Entity) dönmesi beklenir.
   (İş Kuralı: Aynı anda yalnızca bir aktif Sprint olabilir.)

 - test_close_sprint_returns_unfinished_stories_to_backlog:
   Aktif bir Sprint'e "InProgress" durumunda bir User Story eklenir,
   ardından Sprint kapatılır (POST .../sprints/{id}/close).
   HTTP 200 dönmesi, Sprint durumunun "closed" olması ve tamamlanmamış
   story'nin sprint_id alanının null'a dönmesi (backlog'a geri atılması)
   beklenir. (BR-08: Tamamlanmamış story'ler backlog'a döner.)

---

## TaskWorkflowTest

Task Workflow Testleri

Bu test sınıfı User Story içindeki alt görevlerin (Task) yönetimini doğrular.
Task'lar için öngörülen atama (assign) mekanizmaları ve durum geçişleri kontrol edilir.

Test Edilen Senaryolar:
- test_user_can_create_task: Yeni task yaratılması.
- test_task_can_be_assigned: Bir task'ın bir kullanıcıya atanması.
- test_unassigned_task_cannot_be_started: Atanmamış (assignee'si null) olan task'a In Progress değeri verilmemesi.
- test_invalid_task_status_transition_throws_exception: Yanlış durum geçişlerinde exception durumu.

---

## UserStoryCreationTest

User Story Oluşturma Testi

Bu test sınıfı User Story oluşturma sürecinin hem API endpoint'i hem de
Service katmanı üzerinden doğru çalıştığını doğrular. Livewire backlog
bileşenindeki `createStory` metodunun çağırdığı Service metodu ile
API controller'ın çağırdığı aynı Service metodunun argüman uyumluluğu
burada garanti altına alınır.

Test Edilen Senaryolar:
- test_owner_can_create_story_via_api: Owner rolündeki kullanıcı API üzerinden story oluşturur.
- test_moderator_can_create_story_via_api: Moderator rolündeki kullanıcı API üzerinden story oluşturur.
- test_member_cannot_create_story_via_api: Member rolündeki kullanıcı story oluşturamaz (403).
- test_service_creates_story_with_correct_defaults: Service üzerinden story oluşturulduğunda varsayılan alanlar doğru olur.
- test_story_creation_requires_title: Title alanı olmadan story oluşturulamaz (422).

---

## UserStoryWorkflowTest

User Story Workflow Testleri

Bu test sınıfı; User Story kayıtlarının CRUD işlemleri, Backlog'a varsayılan kaydı
ve Scrum (durum geçişleri) kurallarının düzgün çalışıp çalışmadığını kontrol eder.

Test Edilen Senaryolar:
- test_story_created_in_backlog_with_new_status: Yeni story'nin sprint'siz backlog'a eklendiği.
- test_story_can_be_moved_to_sprint: Story'nin Backlog'dan Sprint'e alınması.
- test_valid_status_transitions: Story durumlarının sırayla geçişi.
- test_invalid_status_transition_throws_exception: Yasaklı geçişlerin engellenmesi.

---

## IssueListTest

L-05 & L-06: IssueList Livewire component testi.

Issue listesi render, filtreleme, issue oluşturma, durum değişikliği ve silme testleri.

Test Edilen Senaryolar:
 - **test_issue_list_page_renders**:
   Issue list page renders

 - **test_issue_list_displays_issues**:
   Issue list displays issues

 - **test_create_issue_via_livewire**:
   Create issue via livewire

 - **test_create_issue_validates_title**:
   Create issue validates title

 - **test_filter_issues_by_status**:
   Filter issues by status

 - **test_change_issue_status**:
   Change issue status

 - **test_delete_issue**:
   Delete issue

 - **test_edit_issue**:
   Edit issue

---

## ProjectSettingsTest

L-09 & L-10: ProjectSettings (MemberManager) Livewire component testi.

Üye ekleme, rol değiştirme, üye çıkarma ve proje güncelleme testleri.

Test Edilen Senaryolar:
 - **test_settings_page_renders**:
   Settings page renders

 - **test_can_update_project_name**:
   Can update project name

 - **test_project_name_is_required**:
   Project name is required

 - **test_add_member_by_email**:
   Add member by email

 - **test_add_member_with_invalid_email_shows_error**:
   Add member with invalid email shows error

 - **test_add_duplicate_member_shows_error**:
   Add duplicate member shows error

 - **test_change_member_role**:
   Change member role

 - **test_remove_member**:
   Remove member

 - **test_delete_project**:
   Delete project

 - **test_cannot_add_member_when_max_limit_reached**:
   Maksimum 5 üye limitine ulaşıldığında Livewire üzerinden yeni üye eklenmesi engellenir (BR-12.1).

---

## BacklogTest

L-03 & L-04: Backlog Livewire component testi.

Backlog render, story oluşturma, filtreleme, sıralama ve sprint'e taşıma testleri.

Test Edilen Senaryolar:
 - **test_backlog_page_renders**:
   Backlog page renders

 - **test_backlog_shows_empty_state**:
   Backlog shows empty state

 - **test_backlog_displays_stories**:
   Backlog displays stories

 - **test_create_story_via_backlog**:
   Create story via backlog

 - **test_create_story_validates_title**:
   Create story validates title

 - **test_create_story_resets_form**:
   Create story resets form

 - **test_backlog_filter_by_epic**:
   Backlog filter by epic

 - **test_move_story_to_sprint**:
   Move story to sprint

 - **test_reorder_stories**:
   Reorder stories

---

## KanbanBoardTest

L-01 & L-02: Kanban Board Livewire component testi.

Board render, sprint seçimi, story durum değişikliği ve task status toggle testleri.

Test Edilen Senaryolar:
 - **test_kanban_board_page_renders**:
   Kanban board page renders

 - **test_board_shows_empty_state_without_sprint**:
   Board shows empty state without sprint

 - **test_board_auto_selects_active_sprint**:
   Board auto selects active sprint

 - **test_board_displays_stories_in_columns**:
   Board displays stories in columns

 - **test_change_story_status_action**:
   Change story status action

 - **test_change_task_status_toggle**:
   Change task status toggle

 - **test_invalid_story_status_transition_shows_error**:
   Invalid story status transition shows error

---

## StoryDetailTest

L-11 & L-12: StoryDetail Livewire component testi.

Story detay sayfasında Epic atama, Task atama ve Task status yönetimi testleri.

Test Edilen Senaryolar:
 - **test_story_detail_page_renders**:
   Story detay sayfasının doğru render edilmesi.

 - **test_story_displays_epic_badge**:
   Story'ye bağlı Epic badge gösterimi.

 - **test_assign_epic_to_story**:
   Story'ye epic atama işlemi.

 - **test_remove_epic_from_story**:
   Story'den epic kaldırma işlemi.

 - **test_epic_dropdown_shows_project_epics**:
   Epic dropdown'ında proje epic'leri listelenir.

 - **test_assign_member_to_task**:
   Task'a proje üyesi atama.

 - **test_unassign_member_from_task**:
   Task'tan üye kaldırma.

 - **test_task_shows_assignee_name**:
   Atanmış task'ta üye adı gösterimi.

 - **test_change_task_status_to_in_progress**:
   Task durumunu New'den InProgress'e geçirme.

 - **test_change_task_status_to_done**:
   Task durumunu InProgress'den Done'a geçirme.

 - **test_unassigned_task_cannot_start**:
   Atanmamış task başlatılamaz (BR-16).

 - **test_task_status_dropdown_shows_available_transitions**:
   Her task için sadece geçerli geçişler gösterilir.

 - **test_member_can_change_status_of_task_they_created**:
   Kendi oluşturduğu task'ın durumunu değiştirebilir (P19 — creator yetkisi).

 - **test_member_cannot_change_status_of_task_created_by_others**:
   Başkasının oluşturduğu task'ın durumunu değiştiremez — AuthorizationException (P19).

---

## CalculateBurndownActionTest

U-08 & U-09: CalculateBurndownAction testi.

İdeal çizgi hesaplama ve scope change algılama dahil burndown verisi doğrulaması.

Test Edilen Senaryolar:
 - **test_ideal_line_starts_at_total_points**:
   Ideal line starts at total points

 - **test_empty_sprint_returns_zero_points**:
   Empty sprint returns zero points

 - **test_scope_changes_are_included**:
   Scope changes are included

 - **test_result_structure_is_correct**:
   Result structure is correct

---

## CalculateEpicCompletionTest

Epic Tamamlanma Yüzdesi Hesaplama Testi (CalculateEpicCompletionAction)

Bu test sınıfı, bir Epic'e bağlı User Story'lerin durumlarına göre
Epic'in tamamlanma yüzdesinin ve genel durumunun (status) doğru
hesaplanıp hesaplanmadığını test eder.

Kullanılan Action: CalculateEpicCompletionAction
Bağımlılıklar: Epic modeli, UserStory modeli, StoryStatus enum

Test Edilen Senaryolar:
 - test_empty_epic_has_zero_completion:
   Hiçbir User Story içermeyen boş bir Epic oluşturulur.
   Tamamlanma yüzdesinin 0 olması ve Epic durumunun "New" olması beklenir.

 - test_completion_calculated_correctly:
   3 User Story oluşturulur; bunlardan 2'si "Done", 1'i henüz tamamlanmamış.
   Tamamlanma yüzdesinin floor(2/3 * 100) = 66 olması ve durumun
   "InProgress" olması beklenir.

 - test_all_done_makes_epic_done:
   3 User Story oluşturulur ve hepsi "Done" durumuna getirilir.
   Tamamlanma yüzdesinin 100 olması ve Epic durumunun "Done" olması beklenir.

---

## CalculateVelocityActionTest

U-10: CalculateVelocityAction testi.

Sprint bazında velocity hesaplamasını doğrular.

Test Edilen Senaryolar:
 - **test_calculates_velocity_for_closed_sprints**:
   Calculates velocity for closed sprints

 - **test_ignores_planning_and_active_sprints**:
   Ignores planning and active sprints

 - **test_limits_to_requested_sprint_count**:
   Limits to requested sprint count

 - **test_only_counts_done_stories**:
   Only counts done stories

---

## ChangeStoryStatusActionTest

U-01 & U-02: ChangeStoryStatusAction testi.

Geçerli ve geçersiz durum geçişlerini izole Action seviyesinde test eder.

Test Edilen Senaryolar:
 - **test_new_story_can_transition_to_in_progress**:
   New story can transition to in progress

 - **test_in_progress_story_can_transition_to_done**:
   In progress story can transition to done

 - **test_in_progress_story_can_transition_back_to_new**:
   In progress story can transition back to new

 - **test_done_story_can_transition_to_in_progress**:
   Done story can transition to in progress

 - **test_new_story_cannot_transition_to_done**:
   New story cannot transition to done

 - **test_done_story_cannot_transition_to_new**:
   Done story cannot transition to new

---

## AddMemberActionTest

AddMemberAction Unit Testi.

Proje üye ekleme iş kurallarını doğrular:
- Maksimum 5 üye limiti (BR-11/BR-12.1)
- Tekil üyelik kontrolü (BR-12)
- Başarılı üye ekleme

Test Edilen Senaryolar:
 - **test_cannot_add_member_when_max_limit_reached**:
   Proje zaten 5 üyeye sahipken yeni üye eklemeye çalışılır.
   MaxMembersExceededException fırlatılması beklenir.

 - **test_cannot_add_duplicate_member**:
   Zaten projede olan kullanıcı tekrar eklenmeye çalışılır.
   DuplicateMemberException fırlatılması beklenir.

 - **test_can_add_member_successfully**:
   Limitlerin altında ve mevcut olmayan kullanıcı başarıyla eklenir.

---

## ChangeTaskStatusActionTest

Task Durum Değişikliği Testi (ChangeTaskStatusAction)

Bu test sınıfı, bir Task'ın durumunun (status) değiştirilmesi sırasında
uygulanan iş kurallarını doğrular. Özellikle atanmamış görevlerin
başlatılamaması kuralını ve atanmış görevlerin sorunsuz başlayabilmesini test eder.

Kullanılan Action: ChangeTaskStatusAction
Bağımlılıklar: Task modeli, User modeli, UserStory modeli, TaskStatus enum
İlgili Exception: TaskNotAssignedException

Test Edilen Senaryolar:
 - test_cannot_start_unassigned_task:
   Kimseye atanmamış (assigned_to = null) bir Task oluşturulur ve
   durumu "InProgress"a çekilmeye çalışılır. TaskNotAssignedException
   fırlatılması beklenir. (İş Kuralı: Atanmamış görev başlatılamaz.)

 - test_assigned_task_can_start:
   Bir kullanıcıya atanmış Task oluşturulur ve durumu "InProgress"a çekilir.
   Task durumunun başarıyla InProgress'e geçmesi beklenir.

---

## CloseSprintActionTest

U-05: CloseSprintAction testi.

Sprint kapatıldığında tamamlanmamış story'lerin backlog'a dönmesini test eder.

Test Edilen Senaryolar:
 - **test_close_sprint_transitions_to_closed**:
   Close sprint transitions to closed

 - **test_unfinished_stories_return_to_backlog**:
   Unfinished stories return to backlog

 - **test_all_done_stories_stay_in_sprint**:
   All done stories stay in sprint

---

## DetectScopeChangeActionTest

U-06: DetectScopeChangeAction testi.

Sprint scope change kaydı oluşturulmasını test eder.

Test Edilen Senaryolar:
 - **test_creates_scope_change_record**:
   Creates scope change record

 - **test_creates_removed_scope_change**:
   Creates removed scope change

---

## StartSprintActionTest

Sprint Başlatma Testi (StartSprintAction)

Bu test sınıfı, "Planning" aşamasındaki bir Sprint'in başlatılması
(Active durumuna geçirilmesi) sürecindeki iş kurallarını doğrular.
Özellikle aynı projede birden fazla aktif Sprint olmaması kuralını test eder.

Kullanılan Action: StartSprintAction
Bağımlılıklar: Project modeli, Sprint modeli, SprintStatus enum
İlgili Exception: ActiveSprintAlreadyExistsException

Test Edilen Senaryolar:
 - test_can_start_planning_sprint:
   "Planning" durumundaki bir Sprint oluşturulur ve başlatılır.
   Sprint durumunun "Active" olması ve start_date alanının bugünün
   tarihi ile doldurulması beklenir.

 - test_cannot_start_when_active_sprint_exists:
   Projede zaten aktif bir Sprint varken ikinci bir Sprint başlatılmaya
   çalışılır. ActiveSprintAlreadyExistsException fırlatılması beklenir.
   (İş Kuralı: Bir projede aynı anda yalnızca bir aktif Sprint olabilir.)

---

## ExampleTest

Temel Unit Test Örneği

Bu test sınıfı, PHPUnit test altyapısının düzgün çalışıp çalışmadığını
doğrulamak için kullanılan basit bir "smoke test" niteliğindedir.

Test Edilen Senaryo:
 - test_that_true_is_true: assertTrue ile true değerinin gerçekten true
   olduğunu kontrol eder. Herhangi bir iş mantığı test etmez; yalnızca
   test ortamının sağlıklı şekilde ayağa kalktığını garanti eder.

Test Edilen Senaryolar:
 - **test_that_true_is_true**:
   That true is true

---

## HasStateMachineTraitTest

U-12: HasStateMachine trait testi.

canTransitionTo, transitionTo ve availableTransitions metodlarını test eder.

Test Edilen Senaryolar:
 - **test_can_transition_to_returns_true_for_valid_transition**:
   Can transition to returns true for valid transition

 - **test_can_transition_to_returns_false_for_invalid_transition**:
   Can transition to returns false for invalid transition

 - **test_transition_to_changes_status**:
   Transition to changes status

 - **test_transition_to_throws_on_invalid_transition**:
   Transition to throws on invalid transition

 - **test_available_transitions_returns_correct_list**:
   Available transitions returns correct list

 - **test_sprint_state_machine_works**:
   Sprint state machine works

---

## ProjectRoleEnumTest

U-11: ProjectRole enum hiyerarşi testi.

Rol sıralamasının (rank) ve isAtLeast() metodunun doğru çalıştığını test eder.

Test Edilen Senaryolar:
 - **test_owner_has_highest_rank**:
   Owner has highest rank

 - **test_owner_is_at_least_all_roles**:
   Owner is at least all roles

 - **test_moderator_is_at_least_moderator_and_member**:
   Moderator is at least moderator and member

 - **test_member_is_only_at_least_member**:
   Member is only at least member

 - **test_labels_are_defined**:
   Labels are defined

---

