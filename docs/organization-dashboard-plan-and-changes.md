# خطة وتفاصيل تكامل لوحة تحكم المؤسسة

> هذا الملف موجود بنسخة متطابقة داخل مستودعي الـBackend والـFrontend ليكون المرجع المشترك لتنفيذ لوحة تحكم مالك المؤسسة وموظفي المؤسسة.

## معلومات الوثيقة

| البند | القيمة |
|---|---|
| الحالة | مكتملة ومطبقة |
| تاريخ آخر تحديث | 2026-07-31 |
| نطاق العمل | Organization Owner Dashboard + Organization Staff Dashboard |
| Backend | Laravel API داخل `BE-JOD/jod` |
| Frontend | Next.js داخل `JOD-FrontEnd` |
| خارج النطاق | Organization Notifications وMobile Backend Integration |

---

## 1. الهدف من الخطة

الهدف هو بناء تكامل Full Stack موحد للوحة تحكم المؤسسة، بحيث:

- يكون الـBackend هو المصدر الأساسي للصلاحيات والعقود والبيانات.
- تستخدم واجهتا المالك والموظف نفس Organization APIs.
- تختلف العناصر والصفحات والعمليات المتاحة حسب صلاحيات المستخدم.
- تمنع الـPolicies أي وصول إلى بيانات مؤسسة أخرى.
- تتطابق أسماء الصلاحيات بين الـBackend والـFrontend.
- تستبدل البيانات الثابتة في الأقسام المكتملة ببيانات حقيقية من الـAPI.
- تستخدم العمليات ذات دورة الحياة Endpoint موحداً للحالة بدلاً من ربط الواجهة بمسارات متفرقة.

---

## 2. نطاق التنفيذ

### داخل النطاق

- Authentication Context وإعادة تحميل الصلاحيات.
- فصل أدوار Admin وOrganization Owner وOrganization Staff.
- Overview Dashboard.
- Staff Management.
- Roles and Permission Catalog.
- Campaigns.
- Posts.
- Donors.
- Applicants.
- Reports.
- Organization Audit Log.
- Organization Profile and Settings.
- Staff Personal Profile.
- Route Guards وNavigation Guards.
- Backend feature tests والعقود المشتركة.

### خارج النطاق الحالي

- Organization Notifications UI/API Integration.
- Mobile Application Backend Integration.
- Mobile-only compatibility endpoints.

ملفات الإشعارات الحالية لم تُحذف، لكن روابطها مخفية ومحميّة ضمن لوحة المؤسسة إلى أن تبدأ مرحلة الإشعارات بشكل مستقل.

---

## 3. المبادئ المعمارية

### 3.1 الـBackend هو مصدر الصلاحيات

يتم تحميل سياق لوحة التحكم من:

```http
GET /api/v1/me/dashboard-context
```

ويحتوي على:

- بيانات المستخدم.
- نوع لوحة التحكم.
- المؤسسة المرتبط بها المستخدم.
- الصلاحيات بشكل هرمي داخل `modules`.
- الصلاحيات بشكل مباشر داخل `flat`.
- قائمة الصلاحيات الممنوحة داخل `granted`.

إذا كان المستخدم غير Admin وغير مرتبط بمؤسسة، يرجع الـBackend استجابة `422` بدلاً من بناء سياق ناقص.

### 3.2 Organization Scoping

كل موارد المؤسسة تخضع إلى `organization_id` الخاص بالمستخدم الحالي. لا تعتمد الحماية على إخفاء الأزرار فقط، بل على:

- Laravel Policies.
- Resource ownership checks.
- Service-level filters.
- Route authorization.

### 3.3 Permission-aware Frontend

الـFrontend يستخدم `AuthProvider` والدالة:

```ts
can('permission.name')
```

للتحكم في:

- Sidebar items.
- Section tabs.
- أزرار الإنشاء والتعديل والحذف.
- Lifecycle actions.
- تشغيل Queries الاختيارية.
- حماية الروابط المباشرة.
- التحويل إلى أول صفحة مسموحة.

### 3.4 العقود الموحدة

تم اعتماد Endpoints موحدة لتحديث الحالة:

```http
PATCH /api/v1/org/campaigns/{campaign}/status
PATCH /api/v1/org/posts/{post}/status
PATCH /api/v1/org/reports/{report}/status
```

المسارات القديمة ما زالت موجودة في الـBackend للتوافق المؤقت، لكن الواجهة الجديدة تستخدم العقود الموحدة.

---

## 4. مراحل التنفيذ

| المرحلة | المحتوى | الحالة |
|---|---|---|
| Phase 1 | Authentication Context وPermission Navigation | مكتملة |
| Phase 2 | Staff, Roles, Permission Catalog | مكتملة |
| Phase 3 | Organization Overview | مكتملة |
| Phase 4 | Campaigns and Posts Lifecycle | مكتملة |
| Phase 5 | Donors and Applicants | مكتملة |
| Phase 6 | Reports and Audit Log | مكتملة |
| Phase 7 | Profile and Settings | مكتملة |
| Phase 8 | Tests, Cleanup, Documentation | مكتملة |

---

## 5. التعديلات حسب كل قسم

## 5.1 Authentication and Permissions

### تعديلات الـBackend

- تحديث `/me/dashboard-context` ليعيد سياقاً كاملاً للمؤسسة والصلاحيات.
- إضافة مجموعة صلاحيات سجل نشاط المؤسسة `org.audit_logs`.
- إضافة صلاحية تحديث التقارير `org.reports.update`.
- إزالة صلاحيات إشعارات المؤسسة من Permission Catalog القابل للإسناد في هذه المرحلة.
- منع إنشاء Dashboard Context لمستخدم مؤسسة غير مرتبط بمؤسسة.
- عدم تضمين Notification counters ضمن سياق لوحة المؤسسة الحالي.

### تعديلات الـFrontend

- تحميل Dashboard Context داخل `AuthProvider` عند تسجيل الدخول أو Refresh.
- إضافة `can(permission)` كمصدر موحد للتحقق من الصلاحيات.
- حماية Layout الخاص بلوحات التحكم.
- فلترة Sidebar وSection Tabs حسب الصلاحيات.
- فصل مسارات Admin عن Owner وStaff.
- تحويل المستخدم إلى أول Route مسموح عند فتح Route غير مسموح.
- إخفاء Organization Notification routes من التنقل الحالي.

---

## 5.2 Staff Management, Roles, and Permission Catalog

### تعديلات الـBackend

- توحيد عقد الأدوار لقبول `isActive` من الواجهة وتحويله إلى `is_active`.
- إعادة `roleId` ضمن Staff Resource.
- حماية Staff وRoles بالـPolicies.
- منع العمليات الحساسة التي قد تلغي آخر مالك أو تعدّل أدوار النظام بطريقة غير آمنة.
- توفير Permission Catalog من:

```http
GET /api/v1/org/permissions/catalog
```

- الإبقاء على Staff وRoles كإدارة Owner-only وغير قابلة للإسناد لموظف عادي.

### تعديلات الـFrontend

- إزالة الأدوار وPermission Catalog الثابتة.
- تحميل الأدوار من:

```http
GET /api/v1/org/staff/roles
```

- استخدام `organizationRoleId` عند إنشاء وتعديل الموظف.
- ربط نماذج الموظف والدور بالـAPI الحقيقي.
- إظهار صفحات Staff وRoles للمالك فقط.
- بناء حقول الصلاحيات ديناميكياً من Permission Catalog.

---

## 5.3 Organization Overview

### تعديلات الـBackend

توفير:

```http
GET /api/v1/org/dashboard/overview
```

والاستجابة تشمل:

- إحصائيات الحملات.
- إحصائيات المنشورات.
- إحصائيات المتبرعين والمتقدمين والتقارير حسب الصلاحية.
- `recentActivity` بدلاً من المفتاح القديم `activity`.

### تعديلات الـFrontend

- إزالة Overview الثابت للمالك والموظف.
- إنشاء Component مشترك لواجهتي Owner وStaff.
- تحميل البيانات من `/org/dashboard/overview`.
- عدم عرض إحصائية لا يملك المستخدم صلاحية قسمها.
- إضافة Loading وError وEmpty states.

---

## 5.4 Campaigns

### تعديلات الـBackend

- CRUD كامل عبر:

```http
/api/v1/org/campaigns
```

- Endpoint موحد للحالة:

```http
PATCH /api/v1/org/campaigns/{campaign}/status
```

- الحالات المدعومة:
  - `draft`
  - `active`
  - `closed`
- دعم `closedReason` عند الإغلاق.
- حماية العرض والإنشاء والتعديل والإغلاق والحذف بواسطة Policies.
- منع الوصول إلى حملة تتبع مؤسسة أخرى.

### تعديلات الـFrontend

- استبدال مسار `/close` بالعقد الموحد `/status`.
- إرسال:

```json
{
  "status": "closed",
  "closedReason": "..."
}
```

- حماية الأزرار باستخدام:
  - `org.campaigns.create`
  - `org.campaigns.update`
  - `org.campaigns.close`
  - `org.campaigns.delete`
- الإبقاء على العرض حسب `org.campaigns.view`.
- استخدام API data بدلاً من بيانات محلية للعمليات الرئيسية.

---

## 5.5 Posts

### تعديلات الـBackend

- CRUD كامل عبر:

```http
/api/v1/org/posts
```

- Endpoint موحد للحالة:

```http
PATCH /api/v1/org/posts/{post}/status
```

- الحالات المدعومة:
  - `draft`
  - `published`
  - `archived`
- تطبيق قواعد الانتقال داخل الـBackend.
- حماية Publish وArchive وRestore كل واحدة بصلاحيتها.

### تعديلات الـFrontend

- إيقاف استخدام مسارات Publish/Archive/Restore المنفصلة في الواجهة الجديدة.
- ربط العمليات بالعقد الموحد:

```json
{ "status": "published" }
{ "status": "archived" }
{ "status": "draft" }
```

- حماية العمليات باستخدام:
  - `org.posts.create`
  - `org.posts.update`
  - `org.posts.publish`
  - `org.posts.archive`
  - `org.posts.restore`
  - `org.posts.delete`

---

## 5.6 Donors

### تعديلات الـBackend

- CRUD كامل عبر:

```http
/api/v1/org/donors
```

- فلترة البيانات حسب مؤسسة المستخدم.
- حماية View/Create/Update/Delete بالـPolicies.

### تعديلات الـFrontend

- ربط القائمة والنماذج والحذف بالـAPI.
- تشغيل Donors Query فقط عند فتح قسم المتبرعين وامتلاك صلاحية العرض.
- حماية الأزرار باستخدام:
  - `org.donors.create`
  - `org.donors.update`
  - `org.donors.delete`

---

## 5.7 Applicants

### تعديلات الـBackend

- CRUD مستقل عبر:

```http
/api/v1/org/applicants
```

- عدم دمج المتقدمين مع المتبرعين في مسار واحد.
- فلترة النتائج حسب المؤسسة.
- حماية View/Create/Update/Delete بالـPolicies.

### تعديلات الـFrontend

- استخدام Applicants Query مستقل.
- عدم تشغيل Query المتقدمين أثناء عرض المتبرعين والعكس صحيح.
- حماية العمليات باستخدام:
  - `org.applicants.create`
  - `org.applicants.update`
  - `org.applicants.delete`

---

## 5.8 Reports

### تعديلات الـBackend

- عرض القائمة والتفاصيل عبر:

```http
GET /api/v1/org/reports
GET /api/v1/org/reports/{report}
```

- إضافة Endpoint موحد للحالة:

```http
PATCH /api/v1/org/reports/{report}/status
```

- الانتقالات المعتمدة:
  - `new -> in_progress`
  - `in_progress -> waiting_response`
  - `in_progress -> closed`
  - `waiting_response -> closed`
- الاستفادة من نفس Service methods القديمة لضمان عدم تكرار منطق الأعمال.
- حماية التحديث باستخدام `org.reports.update`.
- منع تحديث تقرير يتبع مؤسسة أخرى.

### تعديلات الـFrontend

- إنشاء Reports service/query خاص بالمؤسسة.
- عرض الحقول الفعلية القادمة من `ReportResource`:
  - `title`
  - `description`
  - `severity`
  - `status`
  - `reporterName`
  - `createdAt`
- إظهار Status control فقط عند امتلاك `org.reports.update`.
- منع عرض انتقال غير صالح بحسب الحالة الحالية.

---

## 5.9 Organization Audit Log

### تعديلات الـBackend

- استخدام:

```http
GET /api/v1/org/audit-logs
```

- السماح للمالك دائماً.
- السماح للموظف عند امتلاك:

```text
org.audit_logs.view
```

- فلترة السجلات حسب مؤسسة الـActor.
- دعم فلاتر:
  - `actorUserId`
  - `action`
  - `from`
  - `to`

### تعديلات الـFrontend

- إزالة استخدام Admin Audit Hook من صفحة المؤسسة.
- إنشاء:
  - `org.audit-logs.services.ts`
  - `org.audit-logs.query.ts`
  - `org.audit-logs.types.ts`
- توجيه الطلبات إلى `/org/audit-logs` بدلاً من `/admin/audit-logs`.
- حماية Route والتنقل بصلاحية `org.audit_logs.view`.

---

## 5.10 Organization Profile and Settings

### تعديلات الـBackend

توفير العقود التالية:

```http
GET   /api/v1/org/settings/profile
PATCH /api/v1/org/settings/profile
GET   /api/v1/org/settings/bank-account
PATCH /api/v1/org/settings/bank-account
```

بيانات المؤسسة تشمل:

- الاسم.
- البريد الإلكتروني.
- الهاتف.
- اسم البنك.
- IBAN.

الحماية تعتمد على:

- `org.settings.view`
- `org.settings.update`

### تعديلات الـFrontend

- إزالة حفظ إعدادات المؤسسة محلياً فقط.
- إنشاء Organization Settings service/query.
- ربط صفحة Settings للمالك والموظف بعقود المؤسسة.
- إظهار الحقول بوضع القراءة فقط عند عدم وجود Update permission.
- فصل Profile إلى:
  - مالك المؤسسة: يعدّل بيانات المؤسسة.
  - موظف المؤسسة: يعدّل حسابه الشخصي من `/me/profile` فقط.

---

## 5.11 Organization Notifications

هذا القسم مؤجل عمداً.

### الوضع الحالي في الـBackend

- قد تبقى Routes أو Models موجودة للتوافق أو التطوير اللاحق.
- صلاحيات Notifications غير معروضة ضمن Permission Catalog القابل للإسناد في هذه المرحلة.

### الوضع الحالي في الـFrontend

- Routes قد تبقى ضمن ملفات Next.js ويتم توليدها أثناء Build.
- لا تظهر في Sidebar أو Tabs.
- Direct route guard يحول المستخدم إلى Route مسموح.
- لا يتم تشغيل Notification API requests من مسار الاستخدام الطبيعي للوحة المؤسسة.

---

## 6. جدول الـAPI الرئيسي

| القسم | Method | Endpoint | الاستخدام |
|---|---|---|---|
| Auth | GET | `/api/v1/me/dashboard-context` | المستخدم والمؤسسة والصلاحيات |
| Overview | GET | `/api/v1/org/dashboard/overview` | إحصائيات ونشاط المؤسسة |
| Campaigns | REST | `/api/v1/org/campaigns` | CRUD الحملات |
| Campaign Status | PATCH | `/api/v1/org/campaigns/{id}/status` | تغيير حالة الحملة |
| Posts | REST | `/api/v1/org/posts` | CRUD المنشورات |
| Post Status | PATCH | `/api/v1/org/posts/{id}/status` | تغيير حالة المنشور |
| Donors | REST | `/api/v1/org/donors` | CRUD المتبرعين |
| Applicants | REST | `/api/v1/org/applicants` | CRUD المتقدمين |
| Staff | REST | `/api/v1/org/staff` | إدارة الموظفين |
| Roles | REST | `/api/v1/org/staff/roles` | إدارة أدوار المؤسسة |
| Permissions | GET | `/api/v1/org/permissions/catalog` | كتالوج الصلاحيات |
| Reports | GET | `/api/v1/org/reports` | قائمة التقارير |
| Report Detail | GET | `/api/v1/org/reports/{id}` | تفاصيل التقرير |
| Report Status | PATCH | `/api/v1/org/reports/{id}/status` | تحديث مسار التقرير |
| Audit Log | GET | `/api/v1/org/audit-logs` | سجل نشاط المؤسسة |
| Org Profile | GET/PATCH | `/api/v1/org/settings/profile` | بيانات المؤسسة |
| Bank Account | GET/PATCH | `/api/v1/org/settings/bank-account` | الحساب البنكي |
| Staff Profile | PATCH | `/api/v1/me/profile` | بيانات الموظف الشخصية |

---

## 7. مصفوفة الصلاحيات

| القسم | صلاحيات العرض والعمليات |
|---|---|
| Dashboard | `dashboard.view` |
| Campaigns | `org.campaigns.view/create/update/close/delete` |
| Posts | `org.posts.view/create/update/publish/archive/restore/delete` |
| Donors | `org.donors.view/create/update/delete` |
| Applicants | `org.applicants.view/create/update/delete` |
| Reports | `org.reports.view/update` |
| Audit Log | `org.audit_logs.view` |
| Settings | `org.settings.view/update` |
| Staff and Roles | Owner-only وليست ضمن الكتالوج القابل للإسناد |
| Notifications | مؤجلة وغير قابلة للإسناد في هذا الإصدار |

أي صلاحية تعديل تتطلب منطقياً صلاحية العرض للقسم نفسه، ويعيد Permission Catalog هذه العلاقة داخل `requires`.

---

## 8. قواعد الحماية

1. إخفاء الزر في الواجهة ليس بديلاً عن Policy في الـBackend.
2. كل Show/Update/Delete يجب أن يتحقق من تطابق `organization_id`.
3. المالك يمتلك صلاحيات الإدارة الحساسة الخاصة بالمؤسسة.
4. الموظف يحصل فقط على الصلاحيات المسندة لدوره.
5. لا تُستخدم Admin APIs داخل صفحات المؤسسة.
6. لا يتم إرسال `organizationId` من الواجهة لتحديد المؤسسة؛ يتم أخذه من المستخدم المصادق عليه.
7. Lifecycle transitions يتم التحقق منها في Service أو Domain logic وليس داخل الواجهة فقط.
8. عند فقدان صلاحية Route، يتم التحويل لأول Route مسموح بدلاً من عرض صفحة ناقصة.

---

## 9. التحقق والاختبارات

### Frontend

تم التحقق بواسطة:

```bash
npm run lint
npm run build
```

النتيجة الحالية:

- Lint نجح بدون Errors.
- توجد Warnings قديمة غير مانعة.
- Production Build نجح.
- TypeScript validation نجح.
- Next.js يعرض تحذير انتقال `middleware` إلى `proxy`.
- لا يوجد Frontend test runner معرف حالياً داخل `package.json`.

### Backend

تمت إضافة Feature tests تغطي:

- Organization scoping.
- Campaign/Post lifecycle contracts.
- Report status contract.
- Audit Log للمالك.
- Audit Log للموظف صاحب الصلاحية.
- رفض الموظف الذي لا يملك صلاحية Audit Log.
- Organization Profile and Bank Settings.

أمر التشغيل:

```bash
php artisan test tests/Feature/Org
```

لم يتم تشغيل الاختبارات داخل بيئة التنفيذ الحالية لأن `php` غير مثبت وظهر:

```text
spawn php ENOENT
```

---

## 10. خريطة الـCommits الرئيسية

### Backend

```text
abe3781 feat(org-dashboard): finalize auth context permissions
6a5ac1d feat(org-dashboard): align staff role contracts
4a2d304 feat(org-dashboard): expose overview activity contract
7b5bb61 feat(org-dashboard): connect reports audit permissions
2e75132 test(org-dashboard): cover reports audit integration
```

### Frontend

```text
777e8ef feat(org-dashboard): enforce auth permission navigation
a51f1b1 feat(org-dashboard): connect staff roles permissions
583ed2a feat(org-dashboard): connect organization overview
dfc7aa0 feat(org-dashboard): align campaign post lifecycle permissions
89683aa feat(org-dashboard): gate donor applicant operations
2d95d5d feat(org-dashboard): connect organization reports audit
6ec4cc2 feat(org-dashboard): connect organization settings
3b176eb feat(org-dashboard): separate owner staff profiles
a58ea43 docs(org-dashboard): record integration verification
```

---

## 11. قواعد تطوير أي قسم جديد

عند إضافة قسم جديد إلى لوحة المؤسسة يجب تنفيذ الخطوات التالية:

1. تعريف Permission Group وActions في الـBackend.
2. إضافتها إلى Permission Catalog إذا كانت قابلة للإسناد.
3. إنشاء Policy والتحقق من Organization Scoping.
4. إنشاء API Resource بعقد camelCase واضح.
5. إنشاء Frontend types وservice وquery keys وquery hooks.
6. حماية Route وSidebar وTabs بصلاحية العرض.
7. حماية كل Action بصلاحيته الخاصة.
8. منع Query غير المسموح بدلاً من الاعتماد على استجابة `403` فقط.
9. إضافة Feature tests للعقد والحماية والتداخل بين المؤسسات.
10. تحديث هذا الملف في مستودعي الـBackend والـFrontend بنفس التعديلات.

---

## 12. الحالة النهائية

- التكامل الأساسي بين Backend وFrontend مكتمل.
- Owner وStaff يستخدمان نفس Organization APIs.
- الصلاحيات متطابقة بين الطرفين.
- Organization scoping مطبق في الـBackend.
- الأقسام المكتملة مرتبطة ببيانات حقيقية.
- الإشعارات والموبايل مؤجلان بوضوح.
- أي تطوير لاحق يجب أن يحافظ على العقود الموحدة وقواعد الصلاحيات المذكورة في هذه الوثيقة.
