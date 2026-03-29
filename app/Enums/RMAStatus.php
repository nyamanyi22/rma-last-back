<?php

namespace App\Enums;

enum RMAStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case IN_REPAIR = 'in_repair';
    case REPAIRED = 'repaired';
    case READY_FOR_SHIPMENT = 'ready_for_shipment';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::UNDER_REVIEW => 'Under Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::IN_REPAIR => 'In Repair',
            self::REPAIRED => 'Repaired',
            self::READY_FOR_SHIPMENT => 'Ready for Shipment',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::UNDER_REVIEW => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'error',
            self::IN_REPAIR => 'secondary',
            self::REPAIRED => 'success',
            self::READY_FOR_SHIPMENT => 'primary',
            self::SHIPPED => 'primary',
            self::DELIVERED => 'success',
            self::COMPLETED => 'success',
            self::CANCELLED => 'default',
        };
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $transitions = [
            self::PENDING      => [self::UNDER_REVIEW, self::APPROVED, self::REJECTED, self::CANCELLED],
            self::UNDER_REVIEW => [self::APPROVED, self::REJECTED],
            // Fast Track: approved → shipped directly (simple returns/exchanges)
            // Technical Track: approved → in_repair (warranty/repairs)
            self::APPROVED     => [self::IN_REPAIR, self::SHIPPED, self::READY_FOR_SHIPMENT],
            // Technical Track: in_repair → shipped directly (skip repaired/ready_for_shipment intermediaries)
            self::IN_REPAIR    => [self::REPAIRED, self::SHIPPED],
            self::REPAIRED     => [self::READY_FOR_SHIPMENT, self::SHIPPED],
            self::READY_FOR_SHIPMENT => [self::SHIPPED],
            // Both tracks end at completed (allow from shipped or delivered)
            self::SHIPPED      => [self::DELIVERED, self::COMPLETED],
            self::DELIVERED    => [self::COMPLETED],
        ];

        return in_array($newStatus, $transitions[$this] ?? []);
    }
}