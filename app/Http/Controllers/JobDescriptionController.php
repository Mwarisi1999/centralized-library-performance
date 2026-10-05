<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class JobDescriptionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load([
            'roles',
            'staffProfile.campus',
            'staffProfile.library',
            'staffProfile.supervisor',
            'staffProfile.position.jobDetail',
        ]);

        $profile = $user->staffProfile;

        return view('job-description.show', [
            'user' => $user,
            'profile' => $profile,
            'position' => $profile?->position,
            'jobDetail' => $profile?->position?->jobDetail,
        ]);
    }
}
