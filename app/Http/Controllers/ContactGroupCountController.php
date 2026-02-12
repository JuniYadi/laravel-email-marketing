<?php

namespace App\Http\Controllers;

use App\Models\ContactGroup;
use Illuminate\Http\JsonResponse;

class ContactGroupCountController extends Controller
{
    public function __invoke(ContactGroup $group): JsonResponse
    {
        $count = $group->contacts()
            ->where('is_invalid', false)
            ->count();

        return response()->json([
            'count' => $count,
        ]);
    }
}
