<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandCollaborationController extends Controller
{
    public function index(Request $request, Brand $brand): View
    {
        abort_unless($brand->user_id === $request->user()->id, 403, 'Unauthorized brand access.');

        $members = $brand->members()->with('user')->latest()->get();
        $approvalRequests = $brand->approvalRequests()->with(['requester', 'approver'])->latest()->get();

        return view('brands.collaboration', compact('brand', 'members', 'approvalRequests'));
    }
}
