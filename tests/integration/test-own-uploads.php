<?php

declare( strict_types = 1 );

final class Own_Uploads_Integration_Test extends WP_UnitTestCase {
    use Branch_Fixtures {
        setUp as private setUpBranchFixtures;
        tearDown as private tearDownBranchFixtures;
    }

    private string $image_path;

    protected function setUp(): void {
        \JayPee\Permissions\Lifecycle::register_role();
        $this->setUpBranchFixtures();
        $this->image_path = tempnam( sys_get_temp_dir(), 'jp-permissions-' );
        file_put_contents( $this->image_path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAABQAAAAUAQMAAAC3R49OAAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAAADqYAAAXcJy6UTwAAAADUExURf8AABniCTcAAAAHdElNRQfqChwUJwWa9C+EAAAAJXRFWHRkYXRlOmNyZWF0ZQAyMDI2LTEwLTI4VDIwOjM5OjA1KzAwOjAwaoSufQAAACV0RVh0ZGF0ZTptb2RpZnkAMjAyNi0xMC0yOFQyMDozOTowNSswMDowMBvZFsEAAAAodEVYdGRhdGU6dGltZXN0YW1wADIwMjYtMTAtMjhUMjA6Mzk6MDUrMDA6MDBMzDceAAAADElEQVQI12NgoC4AAABQAAEiE+h1AAAAAElFTkSuQmCC' ) );
    }

    protected function tearDown(): void {
        if ( file_exists( $this->image_path ) ) {
            unlink( $this->image_path );
        }
        $this->tearDownBranchFixtures();
    }

    public function test_branch_editor_uploads_sideloads_and_edits_own_attachment_only(): void {
        $training = $this->pages['football_training'];
        wp_set_current_user( $this->users['fb'] );
        $other = self::factory()->post->create( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_author' => $this->users['admin'] ) );
        add_filter( 'wp_client_side_media_processing_enabled', '__return_true' );

        $sideload_path = tempnam( sys_get_temp_dir(), 'jp-permissions-sideload-' );
        file_put_contents( $sideload_path, file_get_contents( $this->image_path ) );
        $upload = new WP_REST_Request( 'POST', '/wp/v2/media' );
        $upload->set_file_params( array( 'file' => array(
            'tmp_name' => $this->image_path,
            'name' => 'upload.png',
            'type' => 'image/png',
            'error' => UPLOAD_ERR_OK,
            'size' => filesize( $this->image_path ),
        ) ) );
        $upload->set_param( 'post', $training );
        $upload_response = rest_get_server()->dispatch( $upload );
        $this->assertSame( 201, $upload_response->get_status(), wp_json_encode( $upload_response->get_data() ) );
        $own = (int) $upload_response->get_data()['id'];

        $sideload = new WP_REST_Request( 'POST', '/wp/v2/media/' . $own . '/sideload' );
        $sideload->set_param( 'image_size', 'thumbnail' );
        $sideload->set_header( 'Content-Type', 'image/png' );
        $sideload->set_header( 'Content-Disposition', 'attachment; filename="upload-150x150.png"' );
        $sideload->set_body( file_get_contents( $sideload_path ) );
        $sideload_response = rest_get_server()->dispatch( $sideload );
        $this->assertSame( 200, $sideload_response->get_status(), wp_json_encode( $sideload_response->get_data() ) );
        if ( file_exists( $sideload_path ) ) {
            unlink( $sideload_path );
        }

        $edit = new WP_REST_Request( 'POST', '/wp/v2/media/' . $own );
        $edit->set_param( 'alt_text', 'Training photo' );
        $this->assertSame( 200, rest_get_server()->dispatch( $edit )->get_status() );
        $media_edit = new WP_REST_Request( 'POST', '/wp/v2/media/' . $own . '/edit' );
        $media_edit->set_param( 'src', wp_get_attachment_url( $own ) );
        $media_edit->set_param( 'modifiers', array( array( 'type' => 'rotate', 'args' => array( 'angle' => 90 ) ) ) );
        $media_edit_response = rest_get_server()->dispatch( $media_edit );
        $this->assertSame( 201, $media_edit_response->get_status(), wp_json_encode( $media_edit_response->get_data() ) );
        $this->assertSame( 'Training photo', get_post_meta( $own, '_wp_attachment_image_alt', true ) );

        $other_edit = new WP_REST_Request( 'POST', '/wp/v2/media/' . $other );
        $other_edit->set_param( 'alt_text', 'Not allowed' );
        $this->assertSame( 403, rest_get_server()->dispatch( $other_edit )->get_status() );
        $other_media_edit = new WP_REST_Request( 'POST', '/wp/v2/media/' . $other . '/edit' );
        $other_media_edit->set_param( 'src', 'https://example.org/image.png' );
        $other_media_edit->set_param( 'modifiers', array( array( 'type' => 'rotate', 'args' => array( 'angle' => 90 ) ) ) );
        $this->assertSame( 403, rest_get_server()->dispatch( $other_media_edit )->get_status() );
        $delete = new WP_REST_Request( 'DELETE', '/wp/v2/media/' . $own );
        $this->assertSame( 403, rest_get_server()->dispatch( $delete )->get_status() );
        $this->assertTrue( current_user_can( 'edit_post', $own ) );
        $this->assertFalse( current_user_can( 'edit_post', $other ) );
        remove_filter( 'wp_client_side_media_processing_enabled', '__return_true' );
    }
    public function test_unrestricted_admin_media_capabilities_are_unchanged(): void {
        $attachment = self::factory()->post->create( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_author' => $this->users['fb'] ) );
        wp_set_current_user( $this->users['admin'] );
        $this->assertTrue( current_user_can( 'edit_post', $attachment ) );
        $this->assertTrue( current_user_can( 'delete_post', $attachment ) );
    }
}
