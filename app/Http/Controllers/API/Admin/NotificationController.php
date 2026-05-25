<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        // TODO: Implement notification listing with filtering by mailbox, status, category
    }

    public function store(Request $request)
    {
        // TODO: Implement notification creation and sending
    }

    public function show(Notification $notification)
    {
        // TODO: Implement notification detail view
    }

    public function update(Request $request, Notification $notification)
    {
        // TODO: Implement notification update
    }

    public function destroy(Notification $notification): Response
    {
        // TODO: Implement notification deletion
        return response()->noContent();
    }

    public function updateReadState(Request $request, Notification $notification)
    {
        // TODO: Implement mark as read/unread
    }

    public function resend(Request $request, Notification $notification)
    {
        // TODO: Implement resend notification
    }
}
