<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Support\Mobile\MobileApiResponse;
use App\Support\Mobile\SyrianGovernorates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LookupController extends Controller
{
    public function cities(Request $request): JsonResponse
    {
        return $this->lookup($request, SyrianGovernorates::items(), 'Cities retrieved successfully.');
    }

    public function reportReasons(Request $request): JsonResponse
    {
        return $this->lookup($request, [
            ['id' => 'misleading', 'code' => 'misleading', 'label' => 'محتوى مضلل', 'hint' => 'معلومات غير صحيحة أو غير موثوقة.', 'allowsCustomText' => false, 'sortOrder' => 1],
            ['id' => 'abusive', 'code' => 'abusive', 'label' => 'محتوى مسيء أو غير لائق', 'hint' => 'إساءة، كراهية، أو محتوى غير مناسب.', 'allowsCustomText' => false, 'sortOrder' => 2],
            ['id' => 'fraud', 'code' => 'fraud', 'label' => 'احتيال أو طلب تبرع مشبوه', 'hint' => 'نشاط مالي أو حملة تبدو غير موثوقة.', 'allowsCustomText' => false, 'sortOrder' => 3],
            ['id' => 'impersonation', 'code' => 'impersonation', 'label' => 'انتحال جهة أو شخصية', 'hint' => 'الحساب أو المنشور ينتحل هوية شخص أو منظمة.', 'allowsCustomText' => false, 'sortOrder' => 4],
            ['id' => 'other', 'code' => 'other', 'label' => 'سبب آخر', 'hint' => 'اكتب تفاصيل البلاغ.', 'allowsCustomText' => true, 'sortOrder' => 5],
        ], 'Report reasons retrieved successfully.');
    }

    public function postTypes(Request $request): JsonResponse
    {
        return $this->lookup($request, [
            ['code' => 'volunteer_opportunity', 'label' => 'فرصة تطوع', 'hint' => 'مناسب لطلبات المتطوعين.', 'canCreate' => true, 'canFilter' => true, 'sortOrder' => 1],
            ['code' => 'donation_campaign', 'label' => 'حملة تبرع', 'hint' => 'مناسب لحملات جمع التبرعات.', 'canCreate' => true, 'canFilter' => true, 'sortOrder' => 2],
            ['code' => 'help_request', 'label' => 'طلب مساعدة', 'hint' => 'مناسب لطلبات المساعدة المباشرة.', 'canCreate' => true, 'canFilter' => true, 'sortOrder' => 3],
            ['code' => 'service_offer', 'label' => 'تقديم مساعدة', 'hint' => 'مناسب لعرض خدمة أو مساعدة يمكنك تقديمها.', 'canCreate' => true, 'canFilter' => true, 'sortOrder' => 4],
            ['code' => 'campaign_update', 'label' => 'تحديث حملة', 'hint' => 'منشورات تحديثية مرتبطة بالحملات.', 'canCreate' => false, 'canFilter' => true, 'sortOrder' => 5],
            ['code' => 'awareness', 'label' => 'منشور توعوي', 'hint' => 'محتوى توعوي أو إرشادي.', 'canCreate' => false, 'canFilter' => true, 'sortOrder' => 6],
        ], 'Post types retrieved successfully.');
    }

    public function postStatuses(Request $request): JsonResponse
    {
        return $this->lookup($request, [
            ['code' => 'draft', 'label' => 'مسودة', 'sortOrder' => 1],
            ['code' => 'pending', 'label' => 'قيد المراجعة', 'sortOrder' => 2],
            ['code' => 'active', 'label' => 'منشور', 'apiValue' => 'published', 'sortOrder' => 3],
            ['code' => 'rejected', 'label' => 'مرفوض', 'sortOrder' => 4],
            ['code' => 'archived', 'label' => 'مؤرشف', 'sortOrder' => 5],
        ], 'Post statuses retrieved successfully.');
    }

    public function ctaStates(Request $request): JsonResponse
    {
        return $this->lookup($request, [
            ['code' => 'open', 'label' => 'مفتوح', 'sortOrder' => 1],
            ['code' => 'submitted', 'label' => 'تم التقديم', 'sortOrder' => 2],
            ['code' => 'closed', 'label' => 'مغلق', 'sortOrder' => 3],
        ], 'CTA states retrieved successfully.');
    }

    public function notificationTypes(Request $request): JsonResponse
    {
        return $this->lookup($request, [
            ['code' => 'campaign', 'label' => 'حملة', 'sortOrder' => 1],
            ['code' => 'post', 'label' => 'منشور', 'sortOrder' => 2],
            ['code' => 'donation', 'label' => 'تبرع', 'sortOrder' => 3],
            ['code' => 'help', 'label' => 'مساعدة', 'sortOrder' => 4],
            ['code' => 'applicant', 'label' => 'تطوع', 'sortOrder' => 5],
            ['code' => 'system', 'label' => 'النظام', 'sortOrder' => 6],
        ], 'Notification types retrieved successfully.');
    }

    public function donationFlows(Request $request): JsonResponse
    {
        return $this->lookup($request, [
            ['code' => 'contributed', 'label' => 'تبرعاتي', 'sortOrder' => 1],
            ['code' => 'received', 'label' => 'طلبات تبرع مستلمة', 'sortOrder' => 2],
        ], 'Donation flows retrieved successfully.');
    }

    public function donationStatuses(Request $request): JsonResponse
    {
        return $this->lookup($request, [
            ['code' => 'pending', 'label' => 'بانتظار التواصل', 'sortOrder' => 1],
            ['code' => 'contacting', 'label' => 'جاري التواصل', 'sortOrder' => 2],
            ['code' => 'agreed', 'label' => 'تم الاتفاق', 'sortOrder' => 3],
            ['code' => 'completed', 'label' => 'مكتمل', 'sortOrder' => 4],
            ['code' => 'cancelled', 'label' => 'ملغي', 'sortOrder' => 5],
        ], 'Donation statuses retrieved successfully.');
    }

    /** @param list<array<string, mixed>> $items */
    private function lookup(Request $request, array $items, string $message): JsonResponse
    {
        $search = Str::lower((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'active');
        $items = array_map(static fn (array $item): array => ['isActive' => true, ...$item], $items);

        if ($status === 'active') {
            $items = array_values(array_filter($items, static fn (array $item): bool => $item['isActive'] === true));
        }

        if ($search !== '') {
            $items = array_values(array_filter($items, static function (array $item) use ($search): bool {
                return Str::contains(Str::lower(implode(' ', array_map('strval', $item))), $search);
            }));
        }

        usort($items, static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));
        return MobileApiResponse::success($items, $message);
    }
}
