<?php

declare(strict_types=1);

use App\Http\Resources\Resource;
use App\Http\Responses\ApiResponse;
use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the standardized success envelope from ApiResponse', function (): void {
    $response = ApiResponse::success(
        data: ['foo' => 'bar'],
        message: 'Done.',
        meta: ['count' => 1],
    );

    expect($response->getData(true))->toMatchArray([
        'success' => true,
        'message' => 'Done.',
        'data' => ['foo' => 'bar'],
        'meta' => ['count' => 1],
        'errors' => null,
    ]);
});

it('returns the standardized error envelope from ApiResponse', function (): void {
    $response = ApiResponse::error(
        message: 'Something went wrong.',
        errors: ['field' => ['Invalid']],
        status: 422,
    );

    expect($response->status())->toBe(422)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'message' => 'Something went wrong.',
            'data' => null,
            'meta' => null,
            'errors' => ['field' => ['Invalid']],
        ]);
});

it('returns the success envelope from the central health endpoint', function (): void {
    $this->getJson('http://localhost/api/health')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Central API is healthy.')
        ->assertJsonPath('data.context', 'central')
        ->assertJsonPath('meta', null)
        ->assertJsonPath('errors', null);
});

it('returns a laravel-compatible validation error envelope', function (): void {
    $this->postJson('http://localhost/api/__test/validation', [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('data', null)
        ->assertJsonPath('meta', null)
        ->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta',
            'errors' => ['email'],
        ]);
});

it('returns a not found error envelope', function (): void {
    $this->getJson('http://localhost/api/__test/not-found')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Custom not found message.')
        ->assertJsonPath('errors', null);
});

it('returns a forbidden error envelope', function (): void {
    $this->getJson('http://localhost/api/__test/forbidden')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Custom forbidden message.');
});

it('returns an unauthenticated error envelope', function (): void {
    $this->getJson('http://localhost/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors', null);
});

it('wraps api resources in the standardized envelope', function (): void {
    $user = User::factory()->create();

    $resource = (new class($user) extends Resource
    {
        public function toArray($request): array
        {
            return [
                'id' => $this->id,
                'email' => $this->email,
            ];
        }
    })->withMessage('User retrieved successfully.');

    $payload = $resource->response()->getData(true);

    expect($payload)->toMatchArray([
        'success' => true,
        'message' => 'User retrieved successfully.',
        'data' => [
            'id' => $user->id,
            'email' => $user->email,
        ],
        'meta' => null,
        'errors' => null,
    ]);
});
