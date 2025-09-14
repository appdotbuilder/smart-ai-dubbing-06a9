<?php

use App\Models\DubbingJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('displays dubbing interface on welcome page', function () {
    $response = $this->get('/');
    
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => 
        $page->component('welcome')
    );
});

it('allows user to submit video url for dubbing', function () {
    $response = $this->post('/dubbing', [
        'video_url' => 'https://www.youtube.com/watch?v=test123',
        'dubbing_mode' => 'auto',
        'voice_style' => 'natural',
        'output_mode' => 'replace',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');
});

it('allows user to upload video file for dubbing', function () {
    $file = UploadedFile::fake()->create('test-video.mp4', 1024, 'video/mp4');

    $response = $this->post('/dubbing', [
        'video_file' => $file,
        'dubbing_mode' => 'auto',
        'voice_style' => 'natural',
        'output_mode' => 'replace',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');
});

it('validates required fields in dubbing form', function () {
    $response = $this->post('/dubbing', []);

    $response->assertSessionHasErrors(['video_url']);
});

it('validates video url format', function () {
    $response = $this->post('/dubbing', [
        'video_url' => 'not-a-valid-url',
        'dubbing_mode' => 'auto',
        'voice_style' => 'natural',
        'output_mode' => 'replace',
    ]);

    $response->assertSessionHasErrors(['video_url']);
});

it('validates video file type', function () {
    $file = UploadedFile::fake()->create('test-file.txt', 1024, 'text/plain');

    $response = $this->post('/dubbing', [
        'video_file' => $file,
        'dubbing_mode' => 'auto',
        'voice_style' => 'natural',
        'output_mode' => 'replace',
    ]);

    $response->assertSessionHasErrors(['video_file']);
});

it('allows authenticated users to view dashboard with jobs', function () {
    $user = User::factory()->create();
    $jobs = DubbingJob::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) =>
        $page->component('dashboard')
            ->has('jobs', 3)
    );
});

it('redirects guests from dashboard to login', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

it('creates dubbing job model with correct attributes', function () {
    $job = DubbingJob::factory()->create([
        'source_type' => 'url',
        'source_url' => 'https://www.youtube.com/watch?v=test123',
        'dubbing_mode' => 'auto',
        'voice_style' => 'natural',
        'output_mode' => 'replace',
        'status' => 'processing',
        'progress' => 50,
    ]);

    expect($job->source_type)->toBe('url');
    expect($job->source_url)->toBe('https://www.youtube.com/watch?v=test123');
    expect($job->dubbing_mode)->toBe('auto');
    expect($job->voice_style)->toBe('natural');
    expect($job->output_mode)->toBe('replace');
    expect($job->status)->toBe('processing');
    expect($job->progress)->toBe(50);
    expect($job->isProcessing())->toBeTrue();
    expect($job->isCompleted())->toBeFalse();
    expect($job->hasFailed())->toBeFalse();
});

it('can mark dubbing job as completed', function () {
    $job = DubbingJob::factory()->create(['status' => 'processing']);

    $outputFiles = [
        'dubbed_video' => '/storage/videos/dubbed_123.mp4',
        'subtitles' => '/storage/subtitles/123.srt',
    ];

    $job->markAsCompleted($outputFiles);

    expect($job->status)->toBe('completed');
    expect($job->progress)->toBe(100);
    expect($job->output_files)->toBe($outputFiles);
    expect($job->completed_at)->not->toBeNull();
});

it('can mark dubbing job as failed', function () {
    $job = DubbingJob::factory()->create(['status' => 'processing']);

    $errorMessage = 'Video download failed';
    $job->markAsFailed($errorMessage);

    expect($job->status)->toBe('failed');
    expect($job->error_message)->toBe($errorMessage);
    expect($job->hasFailed())->toBeTrue();
});