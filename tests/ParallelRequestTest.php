<?php

namespace Recca0120\LaravelParallel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Carbon;
use Recca0120\LaravelParallel\Tests\Fixtures\User;
use Throwable;

class ParallelRequestTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        ParallelRequest::setBinary(__DIR__.'/Fixtures/artisan');
    }

    public function test_it_should_return_test_response(): void
    {
        $request = ParallelRequest::create()->from('/foo');

        $response = $request->get('/')->wait();

        $response->assertOk()->assertSee('Hello World');
    }

    public function test_it_should_return_previous_url(): void
    {
        $from = '/foo';
        $request = ParallelRequest::create()->from($from);

        $response = $request->get('/previous_url')->wait();

        $response->assertOk()->assertSee($from);
    }

    public function test_it_should_has_db_connection_in_server_variables(): void
    {
        $request = ParallelRequest::create()->withServerVariables(['CUSTOM' => 'custom']);

        $response = $request->getJson('/server_variables')->wait();

        $response->assertOk()->assertJson([
            'DB_CONNECTION' => 'testbench',
            'CUSTOM' => 'custom',
        ]);
    }

    public function test_it_should_return_test_response_with_json_response(): void
    {
        $request = ParallelRequest::create();

        $response = $request->json('GET', '/')->wait();

        $response->assertOk()->assertJson(['content' => 'Hello World']);
    }

    /**
     * @throws Throwable
     */
    public function test_it_should_get_json_ten_times(): void
    {
        $batch = ParallelRequest::create()->times(10);

        $responses = [];
        foreach ($batch->json('GET', '/') as $promise) {
            $responses[] = $promise->wait();
        }

        self::assertCount(10, $responses);
    }

    /**
     * @dataProvider httpStatusCodeProvider
     */
    public function test_it_should_assert_http_status_code(int $code): void
    {
        $response = ParallelRequest::create()->getJson('/status_code/'.$code)->wait();

        $response->assertStatus($code);
    }

    public function test_it_should_show_echo_in_console(): void
    {
        $this->expectOutputRegex('/echo foo/');

        $response = ParallelRequest::create()->get('/echo')->wait();

        $response->assertSee('bar');
    }

    public function test_it_should_show_dump_in_console(): void
    {
        $this->expectOutputRegex('/dump\(foo\)/');

        $response = ParallelRequest::create()->get('/dump')->wait();

        $response->assertSee('bar');
    }

    public function test_it_should_show_dd_in_console(): void
    {
        $this->expectOutputRegex('/dd\(foo\)/');

        ParallelRequest::create()->get('/dd')->wait();
    }

    public function test_it_should_show_generic_user_info(): void
    {
        $user = new GenericUser(['email' => 'recca0120@gmail.com']);
        $request = ParallelRequest::create()->actingAs($user);

        $response = $request->postJson('/user')->wait();

        $response->assertJsonPath('email', 'recca0120@gmail.com');
    }

    public function test_it_should_show_eloquent_user_info(): void
    {
        $request = ParallelRequest::create()->actingAs(User::first(), 'api');

        $response = $request->postJson('/api/user')->wait();

        $response->assertJsonPath('email', 'recca0120@gmail.com');
    }

    public function test_it_should_login_and_get_user_info(): void
    {
        $request = ParallelRequest::create();

        $response = $request->post('/auth/login', [
            'email' => 'recca0120@gmail.com',
            'password' => 'password',
        ])->wait();

        $response->assertJsonPath('email', 'recca0120@gmail.com');
    }

    public function test_it_should_get_user_info_with_token(): void
    {
        $token = '6Uv0zov7V2dAk5wWE45HHHhz05gpsmw2';
        $request = ParallelRequest::create()->withToken($token);

        $response = $request->post('/api/user')->wait();

        $response->assertJsonPath('email', 'recca0120@gmail.com');
    }

    public function test_it_should_get_session_value(): void
    {
        $sessionValue = uniqid('session_', true);
        $request = ParallelRequest::create();
        $request->patch('/session?session='.$sessionValue)->wait();

        $response = $request->getJson('/session')->wait();

        $response->assertJsonPath('session', $sessionValue);
    }

    public function test_it_should_finish_10_requests_in_5_seconds(): void
    {
        $startTime = microtime(true);

        foreach (ParallelRequest::create()->times(10)->get('/sleep') as $promise) {
            $promise->wait();
        }

        self::assertLessThan(5, microtime(true) - $startTime);
    }

    public function test_it_should_set_test_date(): void
    {
        $testDate = Carbon::parse('2023-01-01 00:00:00');
        Carbon::setTestNow($testDate);

        $request = ParallelRequest::create();

        $response = $request->get('/date')->wait();

        $response->assertOk()->assertContent($testDate->toIso8601String());
    }

    /**
     * @return int[][]
     */
    public static function httpStatusCodeProvider(): array
    {
        return array_map(static function ($code) {
            return [$code];
        }, [401, 403, 404, 500, 504]);
    }
}
