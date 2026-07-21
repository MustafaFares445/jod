<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionAction: string
{
    case VIEW = 'view';
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case RESET_PASSWORD = 'reset_password';
    case VERIFY = 'verify';
    case ACCEPT = 'accept';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case CLAIM = 'claim';
    case REQUEST_INFO = 'request_info';
    case CLOSE = 'close';
    case MANAGE = 'manage';
    case PUBLISH = 'publish';
    case ARCHIVE = 'archive';
    case RESTORE = 'restore';
    case SEND = 'send';
    case RESEND = 'resend';

    public function label(): string
    {
        return match ($this) {
            self::VIEW => 'عرض',
            self::CREATE => 'إنشاء',
            self::UPDATE => 'تحديث',
            self::DELETE => 'حذف',
            self::RESET_PASSWORD => 'إعادة تعيين كلمة المرور',
            self::VERIFY => 'توثيق',
            self::ACCEPT => 'قبول',
            self::APPROVE => 'موافقة',
            self::REJECT => 'رفض',
            self::CLAIM => 'استلام',
            self::REQUEST_INFO => 'طلب معلومات',
            self::CLOSE => 'إغلاق',
            self::MANAGE => 'إدارة',
            self::PUBLISH => 'نشر',
            self::ARCHIVE => 'أرشفة',
            self::RESTORE => 'استعادة',
            self::SEND => 'إرسال',
            self::RESEND => 'إعادة إرسال',
        };
    }

    /** @return list<self> */
    public static function crud(): array
    {
        return [self::VIEW, self::CREATE, self::UPDATE, self::DELETE];
    }

    /** @return list<self> */
    public static function readOnly(): array
    {
        return [self::VIEW];
    }
}
