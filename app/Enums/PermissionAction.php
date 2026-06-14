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
            self::VIEW => 'View',
            self::CREATE => 'Create',
            self::UPDATE => 'Update',
            self::DELETE => 'Delete',
            self::RESET_PASSWORD => 'Reset Password',
            self::VERIFY => 'Verify',
            self::ACCEPT => 'Accept',
            self::APPROVE => 'Approve',
            self::REJECT => 'Reject',
            self::CLAIM => 'Claim',
            self::REQUEST_INFO => 'Request Info',
            self::CLOSE => 'Close',
            self::MANAGE => 'Manage',
            self::PUBLISH => 'Publish',
            self::ARCHIVE => 'Archive',
            self::RESTORE => 'Restore',
            self::SEND => 'Send',
            self::RESEND => 'Resend',
        };
    }

    public static function crud(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
        ];
    }

    public static function readOnly(): array
    {
        return [self::VIEW];
    }
}
