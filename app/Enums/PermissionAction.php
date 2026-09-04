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
    case CLOSE = 'close';
    case MANAGE = 'manage';
    case PUBLISH = 'publish';
    case ARCHIVE = 'archive';
    case RESTORE = 'restore';
    case SEND = 'send';
    case RESEND = 'resend';
    case DIAGNOSTICS = 'diagnostics';
    case CONFIGURE = 'configure';
    case MANAGE_URGENCY = 'manage_urgency';
    case MANAGE_OUTCOMES = 'manage_outcomes';

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
            self::CLOSE => 'إغلاق',
            self::MANAGE => 'إدارة',
            self::PUBLISH => 'نشر',
            self::ARCHIVE => 'أرشفة',
            self::RESTORE => 'استعادة',
            self::SEND => 'إرسال',
            self::RESEND => 'إعادة إرسال',
            self::DIAGNOSTICS => 'تشخيص',
            self::CONFIGURE => 'تهيئة',
            self::MANAGE_URGENCY => 'إدارة الاستعجال',
            self::MANAGE_OUTCOMES => 'إدارة النتائج',
        };
    }

    public static function crud(): array
    {
        return [self::VIEW, self::CREATE, self::UPDATE, self::DELETE];
    }

    public static function readOnly(): array
    {
        return [self::VIEW];
    }
}
