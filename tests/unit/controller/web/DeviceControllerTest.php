<?php

namespace Tests\Unit\Controller\Web;

use App\Device;
use App\RFDevice;
use App\User;
use Illuminate\Foundation\Testing\TestResponse;
use Mockery;
use Tests\Unit\Controller\Common\DeviceControllerTestCase;

class DeviceControllerTest extends DeviceControllerTestCase
{
    public function testDevices_GivenUserNotLoggedIn_RedirectToIndex()
    {
        $response = $this->get('/devices');

        $this->assertRedirectedToRouteWith302($response, '/');
        $response->assertSessionMissing(env('SESSION_USER_ID'));
    }

    public function testDevices_GivenUserLoggedIn_ViewContainsUsersName()
    {
        $user = $this->createUser(self::$faker->uuid());

        $mockUserTable = Mockery::mock(User::class);
        $mockUserTable
            ->shouldReceive('where')->with('user_id', $user->user_id)->andReturn(Mockery::self())
            ->shouldReceive('first')->andReturn(Mockery::self())
            ->shouldReceive('getAttribute')->with('name')->andReturn($user->name)
            ->shouldReceive('getAttribute')->with('devices')->andReturn([]);

        $this->app->instance(User::class, $mockUserTable);

        $response = $this->withSession([env('SESSION_USER_ID') => $user->user_id])->get('/devices');

        $response->assertSee($user->name . '\'s Controllable Devices');
        $response->assertStatus(200);
    }

    public function testDevices_GivenUserLoggedIn_ViewContainsUsersDevices()
    {
        $device1Name = self::$faker->word();
        $device2Name = self::$faker->word();
        $device3Name = self::$faker->word();

        $user = $this->givenSingleUserExistsWithDevices($device1Name, $device2Name, $device3Name);

        $response = $this->withSession([env('SESSION_USER_ID') => $user->user_id])->get('/devices');

        $response->assertSee($device1Name);
        $response->assertSee($device2Name);
        $response->assertSee($device3Name);
        $response->assertStatus(200);
    }

    public function testAdd_GivenPostedData_RedirectToDevices()
    {
        $user = $this->givenSingleUserExists();
        $deviceName = self::$faker->word();

        $response = $this->addDeviceForUser($user->user_id, $deviceName);

        $this->assertRedirectedToRouteWith302($response, '/devices');
    }

    public function testAdd_GivenSingleUserExists_CallsAddForModels()
    {
        $user = $this->givenSingleUserExists();
        $deviceName = self::$faker->name();

        $this->addDeviceForUser($user->user_id, $deviceName);
    }

    public function testAdd_GivenSingleUserExists_SessionContainsSuccessMessage()
    {
        $user = $this->givenSingleUserExists();
        $deviceName = self::$faker->name();

        $response = $this->addDeviceForUser($user->user_id, $deviceName);

        $response->assertSessionHas('alert-success');
    }

    public function testDelete_GivenUserDoesNotOwnsDevice_RedirectToDevices()
    {
        $user = $this->givenSingleUserExists();
        $deviceId = self::$faker->randomDigit();

        $this->givenDoesUserOwnDevice($user, $deviceId, false);

        $response = $this->withSession([env('SESSION_USER_ID') => $user->user_id])
            ->get('/devices/delete/' . $deviceId);

        $this->assertRedirectedToRouteWith302($response, '/devices');
    }

    public function testDelete_GivenUserDoesNotOwnsDevice_SessionContainsErrorMessage()
    {
        $user = $this->givenSingleUserExists();
        $deviceId = self::$faker->randomDigit();

        $this->givenDoesUserOwnDevice($user, $deviceId, false);

        $response = $this->withSession([env('SESSION_USER_ID') => $user->user_id])
            ->get('/devices/delete/' . $deviceId);

        $response->assertSessionHas('alert-danger');
    }

    public function testDelete_GivenUserOwnsDevice_RedirectToDevices()
    {
        $user = $this->givenSingleUserExists();
        $deviceId = $this->givenUserOwnsDeviceForDeletion($user);

        $response = $this->withSession([env('SESSION_USER_ID') => $user->user_id])
            ->get('/devices/delete/' . $deviceId);

        $this->assertRedirectedToRouteWith302($response, '/devices');
    }

    public function testDelete_GivenUserOwnsDevice_SessionContainsSuccessMessage()
    {
        $user = $this->givenSingleUserExists();
        $deviceId = $this->givenUserOwnsDeviceForDeletion($user);

        $response = $this->withSession([env('SESSION_USER_ID') => $user->user_id])
            ->get('/devices/delete/' . $deviceId);

        $response->assertSessionHas('alert-success');
    }

    public function testHandleControlRequest_GivenUserExistsWithDevice_CallsPublish()
    {
        $user = $this->givenSingleUserExists();
        $deviceId = self::$faker->randomDigit();
        $action = self::$faker->word();

        $mockUserRecord = $this->givenDoesUserOwnDevice($user, $deviceId, true);
        $mockUserRecord->shouldReceive('getAttribute')->with('user_id')->once()->andReturn($user->user_id);

        $this->mockMessagePublisher();

        $response = $this->callControl($user->user_id, $action, $deviceId);

        $this->assertRedirectedToRouteWith302($response, '/devices');
    }

    public function testHandleControlRequest_GivenUserExistsWithNoDevices_PublishIsNotCalled()
    {
        $user = $this->givenSingleUserExists();
        $deviceId = self::$faker->randomDigit();
        $action = self::$faker->word();

        $this->givenDoesUserOwnDevice($user, $deviceId, false);
        $this->mockMessagePublisher(0);

        $response = $this->callControl($user->user_id, $action, $deviceId);

        $this->assertRedirectedToRouteWith302($response, '/devices');
    }

    public function testHandleControlRequest_GivenUserExistsWithNoDevices_SessionContainsErrorMessage()
    {
        $user = $this->givenSingleUserExists();
        $deviceId = self::$faker->randomDigit();
        $action = self::$faker->word();

        $this->givenDoesUserOwnDevice($user, $deviceId, false);

        $response = $this->callControl($user->user_id, $action, $deviceId);

        $response->assertSessionHas('alert-danger');
    }

    private function callControl(string $userId, string $action, int $deviceId) : TestResponse
    {
        $response = $this->withSession([env('SESSION_USER_ID') => $userId])
            ->post("/devices/$action/$deviceId");

        return $response;
    }

    private function givenUserOwnsDeviceForDeletion(User $user) : int
    {
        $deviceId = self::$faker->randomDigit();

        $mockDeviceModel = Mockery::mock(Device::class);
        $mockDeviceModel
            ->shouldReceive('find')->with($deviceId)->once()->andReturn(Mockery::self())
            ->shouldReceive('getAttribute')->with('name')->once();

        $mockDeviceModel->shouldReceive('destroy')->with($deviceId)->once();
        $this->app->instance(Device::class, $mockDeviceModel);

        $this->givenDoesUserOwnDevice($user, $deviceId, true);

        return $deviceId;
    }

    private function addDeviceForUser(string $userId, string $deviceName) : TestResponse
    {
        $device = $this->createDevice($deviceName, $userId);

        $mockDeviceModel = Mockery::mock(Device::class);
        $mockDeviceModel->shouldReceive('add')->withAnyArgs()->once()->andReturn($device);
        $this->app->instance(Device::class, $mockDeviceModel);

        $mockRFDeviceModel = Mockery::mock(RFDevice::class);
        $mockRFDeviceModel->shouldReceive('add')->withAnyArgs()->once();
        $this->app->instance(RFDevice::class, $mockRFDeviceModel);

        $response = $this->withSession([env('SESSION_USER_ID') => $userId])
            ->call('POST', '/devices/add', [
                'name' => $deviceName,
                'description' => self::$faker->sentence(),
                'onCode' => self::$faker->randomDigit(),
                'offCode' => self::$faker->randomDigit(),
                'pulseLength' => self::$faker->randomDigit()
            ]);

        return $response;
    }
}
