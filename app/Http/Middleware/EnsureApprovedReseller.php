<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Onboarding\Enums\CompanyStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Catalog access: reseller users must belong to an APPROVED company. Internal
 * staff (no company) pass — their catalog access is gated by view_catalog.
 */
class EnsureApprovedReseller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/');
        }

        if ($user->company_id !== null) {
            $company = $user->company;

            if ($company === null || $company->status !== CompanyStatus::Approved) {
                return redirect('/')->with('error', 'Your reseller account is awaiting approval.');
            }
        }

        return $next($request);
    }
}
