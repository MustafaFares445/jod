<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin\Review;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function index(Request $request)
    {
        // TODO: Implement post review listing with filtering
    }

    public function show(Post $post)
    {
        // TODO: Implement post detail view
    }

    public function approve(Request $request, Post $post)
    {
        // TODO: Implement post approval
    }

    public function reject(Request $request, Post $post)
    {
        // TODO: Implement post rejection with reason
    }
}
