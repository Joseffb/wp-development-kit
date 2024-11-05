<?php

use PHPUnit\Framework\TestCase;
use WDK\PostInterface;
use WDK\CommentsHandler;

class CommentsHandlerTest extends TestCase
{
    protected static $post_id;
    protected static $comment_id;

    public static function setUpBeforeClass(): void
    {
        // Create a test post
        $post_arr = [
            'post_title'   => 'Comments Test Post',
            'post_content' => 'Testing comments handling.',
            'post_status'  => 'publish',
        ];
        self::$post_id = wp_insert_post($post_arr);

        // Insert a test comment
        $comment_data = [
            'comment_post_ID'      => self::$post_id,
            'comment_author'       => 'Test Author',
            'comment_author_email' => 'author@example.com',
            'comment_content'      => 'This is a test comment.',
            'comment_approved'     => 1,
        ];
        self::$comment_id = wp_insert_comment($comment_data);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up
        wp_delete_comment(self::$comment_id, true);
        wp_delete_post(self::$post_id, true);
    }

    public function testGetComments()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $comments = $postInterface->comments->getAll();
        $this->assertCount(1, $comments);
        $this->assertEquals('This is a test comment.', $comments[0]->comment_content);
    }

    public function testCreateComment()
    {
        $postInterface = PostInterface::post(self::$post_id);
        $new_comment_id = $postInterface->comments->create([
            'comment_content'      => 'Another test comment.',
            'comment_author'       => 'Another Author',
            'comment_author_email' => 'another@example.com',
        ]);
        $this->assertNotFalse($new_comment_id);

        $comment = get_comment($new_comment_id);
        $this->assertEquals('Another test comment.', $comment->comment_content);

        // Clean up the created comment
        wp_delete_comment($new_comment_id, true);
    }

    public function testCommentUpdate()
    {
        $commentHandler = new \WDK\CommentHandler(get_comment(self::$comment_id));
        $commentHandler->comment_content = 'Updated comment content.';
        $this->assertEquals('Updated comment content.', get_comment(self::$comment_id)->comment_content);
    }

    public function testCommentDeletion()
    {
        $commentHandler = new \WDK\CommentHandler(get_comment(self::$comment_id));
        $commentHandler->delete();
        $this->assertNull(get_comment(self::$comment_id));
    }
}
