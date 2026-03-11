<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\RMARequest;
use App\Models\User;
use App\Models\Product;
use App\Models\RMAComment;
use App\Enums\RMAType;
use App\Enums\RMAReason;
use App\Enums\RMAStatus;
use App\Enums\RMAPriority;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RMARequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_comment_to_rma_request()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a product
        $product = Product::factory()->create();

        // Create an RMA request
        $rma = RMARequest::create([
            'customer_id' => $user->id,
            'product_id' => $product->id,
            'rma_type' => RMAType::RETURN ,
            'reason' => RMAReason::WRONG_ITEM,
            'issue_description' => 'Test issue description',
            'status' => RMAStatus::PENDING,
            'priority' => RMAPriority::LOW,
        ]);

        // Add a comment
        $commentText = 'Test comment';
        $comment = $rma->addComment($user->id, $commentText, 'system', false);

        // Assertions
        $this->assertInstanceOf(RMAComment::class, $comment);
        $this->assertEquals($commentText, $comment->comment);
        $this->assertEquals($user->id, $comment->user_id);
        $this->assertEquals('system', $comment->type);
        $this->assertFalse($comment->notify_customer);

        $this->assertCount(1, $rma->comments);
        $this->assertEquals($commentText, $rma->comments->first()->comment);
    }
}
