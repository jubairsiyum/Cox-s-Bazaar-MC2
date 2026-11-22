<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;

/**
 * In-app alerts for the new product request lifecycle: a branch asks for a
 * product, the Super Admin is told to decide, and the requesting branch hears
 * how it went.
 *
 * Every alert is tagged with the requesting branch, so strict branch isolation
 * (NotificationService::scopeFor) keeps it inside that branch. The Super Admin
 * sees every branch's alerts.
 */
class ProductRequestNotifier
{
    /**
     * A new request: the Super Admin must decide, and the requester gets an
     * acknowledgement that nothing is created until approval.
     */
    public static function submitted(ProductRequest $productRequest): void
    {
        $url = route('products.requests.show', $productRequest);

        NotificationService::notifyMany(
            User::query()->active()->role('Super Admin')->get()
                ->reject(fn (User $user): bool => $user->id === $productRequest->requested_by),
            "New product request '{$productRequest->displayName()}' awaits approval.",
            self::branchName($productRequest).' requested a new product. Submitted by '.($productRequest->requester?->name ?? 'a branch user').'.',
            $url,
            'warning',
            $productRequest->location_id
        );

        if ($productRequest->requester !== null) {
            NotificationService::notify(
                $productRequest->requester,
                "Product request '{$productRequest->displayName()}' submitted.",
                'Your request is awaiting Super Admin approval. No product is added until it is approved.',
                $url,
                'info',
                $productRequest->location_id
            );
        }
    }

    /**
     * Approved: the product now exists in the catalog. The requesting branch's
     * managers and the requester learn that it was added.
     */
    public static function approved(ProductRequest $productRequest, ?User $approver, Product $product): void
    {
        self::notifyRequestingBranch(
            $productRequest,
            "Product request '{$productRequest->displayName()}' approved.",
            ($approver?->name ?? 'The Super Admin').' approved your request and added '.$product->displayName().' ('.$product->product_id.') to the catalog.',
            'success'
        );
    }

    /**
     * Rejected: the requesting branch's managers and the requester learn the
     * reason. Nothing has been added to the catalog.
     */
    public static function rejected(ProductRequest $productRequest, ?User $reviewer, string $reason): void
    {
        self::notifyRequestingBranch(
            $productRequest,
            "Product request '{$productRequest->displayName()}' rejected.",
            ($reviewer?->name ?? 'The Super Admin').' rejected your product request. Reason: '.$reason,
            'error'
        );
    }

    private static function notifyRequestingBranch(ProductRequest $productRequest, string $title, string $body, string $type): void
    {
        NotificationService::notifyMany(
            ManagerLookup::managersFor($productRequest->location_id)->push($productRequest->requester),
            $title,
            $body,
            route('products.requests.show', $productRequest),
            $type,
            $productRequest->location_id
        );
    }

    private static function branchName(ProductRequest $productRequest): string
    {
        return $productRequest->location?->name ?? 'A branch';
    }
}
