<?php

namespace Tests\Feature\Api;

use Faker\Factory as Faker;
use ProcessMaker\Models\User;
use Tests\TestCase;
use Tests\Feature\Shared\RequestHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;

class UsersTest extends TestCase
{

    use RequestHelper;

    const API_TEST_URL = '/users';

    const STRUCTURE = [
        'id',
        'username',
        'email',
        // 'password',
        'firstname',
        'lastname',
        'status',
        'address',
        'city',
        'state',
        'postal',
        'country',
        'phone',
        'fax',
        'cell',
        'title',
        'birthdate',
        'timezone',
        'language',
        'expires_at',
        'updated_at',
        'created_at'
    ];

    /**
     * Test verify the parameter required for create form
     */
    public function testNotCreatedForParameterRequired()
    {
        //Post should have the parameter required
        $response = $this->apiCall('POST', self::API_TEST_URL, []);

        //Validate the header status code
        $response->assertStatus(422);
        $this->assertArrayHasKey('message', $response->json());
    }

    /**
     * Create new user successfully
     */
    public function testCreateUser()
    {
        //Post title duplicated
        $faker = Faker::create();
        $url = self::API_TEST_URL;
        $response = $this->apiCall('POST', $url, [
            'username' => 'newuser',
            'firstname' => 'name',
            'lastname' => 'name',
            'email' => $faker->email,
            'status' => $faker->randomElement(['ACTIVE', 'INACTIVE']),
            'password' => $faker->sentence(10)
        ]);

        //Validate the header status code
        $response->assertStatus(201);
    }

    public function testCreatePreviouslyDeletedUser()
    {
        $url = self::API_TEST_URL;

        $deletedUser = factory(User::class)->create([
            'deleted_at' => '2019-01-01',
            'status' => 'ACTIVE'
        ]);

        $params = [
            'username' => $deletedUser->username,
            'firstname' => 'foo',
            'lastname' => 'bar',
            'email' => $deletedUser->email,
            'status' => 'ACTIVE',
            'password' => 'password123'
        ];

        $response = $this->apiCall('POST', $url, $params);

        $this->assertArrayHasKey('errors', $response->json());

        $this->assertArrayHasKey('username', $response->json()['errors']);

        $this->assertEquals('The Username has already been taken.', $response->json()['errors']['username'][0]);
    }

    public function testDefaultValuesOfUser()
    {
        putenv('APP_TIMEZONE=America/Los_Angeles');
        putenv('DATE_FORMAT=m/d/Y H:i');
        putenv('APP_LANG=en');

        // Create a user without setting fields that have default
        $faker = Faker::create();
        $url = self::API_TEST_URL;
        $response = $this->apiCall('POST', $url, [
            'username' => 'username1',
            'firstname' => 'name',
            'lastname' => 'name',
            'email' => $faker->email,
            'status' => $faker->randomElement(['ACTIVE', 'INACTIVE']),
            'password' => $faker->sentence(10)
        ]);

        $response->assertStatus(201);

        // Validate that the created user has the correct default values
        $createdUser = $response->json();
        $this->assertEquals(getenv('APP_TIMEZONE'), $createdUser['timezone']);
        $this->assertEquals(getenv('DATE_FORMAT'), $createdUser['datetime_format']);
        $this->assertEquals(getenv('APP_LANG'), $createdUser['language']);


        // Create a user setting fields that have default
        $timeZone = 'Test/Test';
        $dateFormat = 'testFormat';
        $faker = Faker::create();
        $url = self::API_TEST_URL;
        $response = $this->apiCall('POST', $url, [
            'username' => 'username2',
            'firstname' => 'name',
            'lastname' => 'name',
            'email' => $faker->email,
            'status' => $faker->randomElement(['ACTIVE', 'INACTIVE']),
            'password' => $faker->sentence(10),
            'timezone' => $timeZone,
            'datetime_format' => $dateFormat
        ]);

        $response->assertStatus(201);

        // Validate that the created user has the correct values
        $createdUser = $response->json();
        $this->assertEquals($createdUser['timezone'], $timeZone);
        $this->assertEquals($createdUser['datetime_format'], $dateFormat);

    }

    /**
     * Can not create a user with an existing username
     */
    public function testNotCreateUserWithUsernameExists()
    {
        factory(User::class)->create([
            'username' => 'mytestusername',
        ]);

        //Post username duplicated
        $faker = Faker::create();
        $response = $this->apiCall('POST', self::API_TEST_URL, [
            'username' => 'mytestusername',
            'email' => $faker->email,
            'deuserion' => $faker->sentence(10)
        ]);

        //Validate the header status code
        $response->assertStatus(422);
        $this->assertArrayHasKey('message', $response->json());
    }

    /**
     * Get a list of Users without query parameters.
     */
    public function testListUser()
    {

        User::query()->delete();

        $faker = Faker::create();

        factory(User::class, 10)->create();

        $response = $this->apiCall('GET', self::API_TEST_URL);

        //Validate the header status code
        $response->assertStatus(200);

        // Verify structure
        $response->assertJsonStructure([
            'data' => ['*' => self::STRUCTURE],
            'meta',
        ]);

        // Verify count
        $this->assertEquals(10, $response->json()['meta']['total']);

    }

    /**
     * Test to verify that the list dates are in the correct format (yyyy-mm-dd H:i+GMT)
     */
    public function testUserListDates()
    {
        $username = 'userTestTimezone';
        $newEntity = factory(User::class)->create(['username' => $username]);
        $route = self::API_TEST_URL . '?filter=' . $username;

        $response = $this->apiCall('GET', $route);

        $this->assertEquals(
            $newEntity->updated_at->format('c'),
            $response->getData()->data[0]->updated_at
        );

        $this->assertEquals(
            $newEntity->created_at->format('c'),
            $response->getData()->data[0]->created_at
        );
    }

    /**
     * Get a list of User with parameters
     */
    public function testListUserWithQueryParameter()
    {
        $username = 'mytestusername';

        factory(User::class)->create([
            'username' => $username,
        ]);

        //List User with filter option
        $perPage = Faker::create()->randomDigitNotNull;
        $query = '?page=1&per_page=' . $perPage . '&order_by=firstname&order_direction=DESC&filter=' . $username;
        $response = $this->apiCall('GET', self::API_TEST_URL . $query);

        //Validate the header status code
        $response->assertStatus(200);

        //verify structure paginate
        $response->assertJsonStructure([
            'data',
            'meta',
        ]);

        // Verify return data
        $this->assertEquals(1, $response->json()['meta']['total']);
        $this->assertEquals('firstname', $response->json()['meta']['sort_by']);
        $this->assertEquals('DESC', $response->json()['meta']['sort_order']);
    }

    /**
     * Tests filtering a user based off of email address
     */
    public function testFetchUserByEmailAddressFilter()
    {
        factory(User::class)->create([
            'email' => 'test@example.com'
        ]);

        $query = '?filter=' . urlencode('test@example.com');
        $response = $this->apiCall('GET', self::API_TEST_URL . $query);

        //Validate the header status code
        $response->assertStatus(200);

        //verify structure paginate
        $response->assertJsonStructure([
            'data',
            'meta',
        ]);

        // Verify return data
        $this->assertEquals(1, $response->json()['meta']['total']);
        $this->assertEquals('test@example.com', $response->json()['data'][0]['email']);
    }

    /**
     * Get a user
     */
    public function testGetUser()
    {
        //get the id from the factory
        $user = factory(User::class)->create()->id;

        //load api
        $response = $this->apiCall('GET', self::API_TEST_URL . '/' . $user);

        //Validate the status is correct
        $response->assertStatus(200);

        //verify structure
        $response->assertJsonStructure(self::STRUCTURE);
    }

    /**
     * Get a user with the memberships
     */
    // public function testGetUserIncledMembership()
    // {
    //     //get the id from the factory
    //     $user = factory(User::class)->create()->id;
    //
    //     //load api
    //     $response = $this->apiCall('GET', self::API_TEST_URL. '/' . $user . '?include=memberships');
    //
    //     //Validate the status is correct
    //     $response->assertStatus(200);
    //
    //     //verify structure
    //     $response->assertJsonFragment(['memberships']);
    // }

    /**
     * Parameters required for update of user
     */
    public function testUpdateUserParametersRequired()
    {
        //The post must have the required parameters
        $url = self::API_TEST_URL . '/' . factory(User::class)->create()->id;

        $response = $this->apiCall('PUT', $url, [
            'username' => ''
        ]);

        //Validate the header status code
        $response->assertStatus(422);
    }

    /**
     * Update user in process
     */
    public function testUpdateUser()
    {
        $faker = Faker::create();

        $url = self::API_TEST_URL . '/' . factory(User::class)->create()->id;

        //Load the starting user data
        $verify = $this->apiCall('GET', $url);

        //Post saved success
        $response = $this->apiCall('PUT', $url, [
            'username' => 'updatemytestusername',
            'email' => $faker->email,
            'firstname' => $faker->firstName,
            'lastname' => $faker->lastName,
            'phone' => $faker->phoneNumber,
            'cell' => $faker->phoneNumber,
            'fax' => $faker->phoneNumber,
            'address' => $faker->streetAddress,
            'city' => $faker->city,
            'state' => $faker->stateAbbr,
            'postal' => $faker->postcode,
            'country' => $faker->country,
            'timezone' => $faker->timezone,
            'status' => $faker->randomElement(['ACTIVE', 'INACTIVE']),
            'birthdate' => $faker->dateTimeThisCentury->format('Y-m-d'),
            'password' => $faker->password(6, 6),
            'force_change_password' => $faker->boolean,
        ]);

        //Validate the header status code
        $response->assertStatus(204);

        //Load the updated user data
        $verify_new = $this->apiCall('GET', $url);

        //Check that it has changed
        $this->assertNotEquals($verify, $verify_new);

    }

    /**
     * Update user in process
     */
    public function testUpdateUserForceChangePasswordFlag()
    {
        $faker = Faker::create();

        $url = self::API_TEST_URL . '/' . factory(User::class)->create()->id;

        //Post saved success
        $response = $this->apiCall('PUT', $url, [
            'username' => 'updatemytestusername',
            'email' => $faker->email,
            'firstname' => $faker->firstName,
            'lastname' => $faker->lastName,
            'status' => $faker->randomElement(['ACTIVE', 'INACTIVE']),
            'password' => $faker->password(6, 6),
            'force_change_password' => 0,
        ]);

        //Validate the header status code
        $response->assertStatus(204);

        //Validate Flag force_change_password was changed
        $this->assertDatabaseHas('users', [
            'force_change_password' => 0
        ]);

    }

    /**
     * Check that the validation wont allow duplicate usernames
     */
    public function testUpdateUserTitleExists()
    {
        $user1 = factory(User::class)->create([
            'username' => 'MyUserName',
        ]);

        $user2 = factory(User::class)->create();

        $url = self::API_TEST_URL . '/' . $user2->id;

        $response = $this->apiCall('PUT', $url, [
            'username' => 'MyUserName',
        ]);
        //Validate the header status code
        $response->assertStatus(422);
        $response->assertSeeText('The Username has already been taken');
    }

    /**
     * Delete user in process
     */
    public function testDeleteUser()
    {
        //Remove user
        $url = self::API_TEST_URL . '/' . factory(User::class)->create()->id;
        $response = $this->apiCall('DELETE', $url);

        //Validate the header status code
        $response->assertStatus(204);
    }

    /**
     * The user does not exist in process
     */
    public function testDeleteUserNotExist()
    {
        //User not exist
        $url = self::API_TEST_URL . '/' . factory(User::class)->make()->id;
        $response = $this->apiCall('DELETE', $url);

        //Validate the header status code
        $response->assertStatus(405);
    }

    /**
     * The user can upload an avatar
     */
    public function testUpdateUserAvatar()
    {
        //Create a new user
        $user = factory(User::class)->create([
            'username' => 'AvatarUser',
        ]);

        //Set our API url for this users
        $url = self::API_TEST_URL . '/' . $user->id;

        //Create a fake image and encode it to base64
        $fakeImage = UploadedFile::fake()
                                 ->image('avatar.jpg', 1200, 1200)
                                 ->size(1500)
                                 ->get();
        $avatar = 'data:image/png;base64,' . base64_encode($fakeImage);

        //Update the user with the fake image as an avatar
        $putResponse = $this->apiCall('PUT', $url, [
            'username' => $user->username,
            'firstname' => 'name',
            'lastname' => 'name',
            'email' => $user->email,
            'status' => 'ACTIVE',
            'avatar' => $avatar,
        ]);

        //Validate the header status code
        $putResponse->assertStatus(204);

        //Request the user from the API
        $getResponse = $this->apiCall('GET', $url);

        //Assert that the 'avatar' key exists
        $getResponse->assertJsonStructure(['avatar']);

        //Assert that the file exists in the correct location
        $json = $getResponse->json();
        $path = parse_url($json['avatar'], PHP_URL_PATH);
        $this->assertFileExists('public' . $path);
    }

    /**
    * Tests the archiving and restoration of a process
    */
    public function testRestoreSoftDeletedUser()
    {
        // create an user
        $user = factory(User::class)->create([
            'email' => 'test@email.com',
            'username' => 'mytestusername'
        ]);
        $id = $user->id;

        // Assert that the user is listed
        $response = $this->apiCall('GET', self::API_TEST_URL);
        $response->assertJsonFragment(['id' => $id]);

        // Soft delete the user
        $response = $this->apiCall('DELETE', self::API_TEST_URL . '/'. $id);
        $response->assertStatus(204);

        // Assert that the user is not listed on the main index
        $response = $this->apiCall('GET', self::API_TEST_URL);
        $response->assertJsonMissing(['id' => $id]);

        // Restore the user by email
        $response = $this->apiCall('PUT', self::API_TEST_URL .'/restore', [
            'email' => $user->email
        ]);
        $response->assertStatus(200);

        // Assert that the user is listed
        $response = $this->apiCall('GET', self::API_TEST_URL);
        $response->assertJsonFragment(['id' => $id]);

        // Soft delete the user
        $response = $this->apiCall('DELETE', self::API_TEST_URL . '/'. $id);
        $response->assertStatus(204);

        // Assert that the user is not listed on the main index
        $response = $this->apiCall('GET', self::API_TEST_URL);
        $response->assertJsonMissing(['id' => $id]);

        // Restore the user by username
        $response = $this->apiCall('PUT', self::API_TEST_URL .'/restore', [
            'username' => $user->username
        ]);
        $response->assertStatus(200);

        // Assert that the user is listed
        $response = $this->apiCall('GET', self::API_TEST_URL);
        $response->assertJsonFragment(['id' => $id]);
    }

    public function testCreateWithoutPassword()
    {
        $payload = [
            "firstname" => "foo",
            "lastname" => "bar",
            "email" => "foobar@test.com",
            "username" => "foobar",
            "status" => "ACTIVE"
        ];
        $response = $this->apiCall('POST', self::API_TEST_URL, $payload);
        $response->assertStatus(422);
        $json = $response->json();
        $this->assertEquals('The Password field is required.', $json['errors']['password'][0]);

        $payload['password'] = 'abc';
        $response = $this->apiCall('POST', self::API_TEST_URL, $payload);
        $response->assertStatus(422);
        $json = $response->json();
        $this->assertEquals('The Password must be at least 6 characters.', $json['errors']['password'][0]);

        $payload['password'] = 'abc123';
        $response = $this->apiCall('POST', self::API_TEST_URL, $payload);
        $response->assertStatus(201);
        $json = $response->json();
        $userId = $json['id'];

        // Test updating the users's password

        $payload['password'] = 'abc';
        $response = $this->apiCall('PUT', route('api.users.update', $userId), $payload);
        $response->assertStatus(422);
        $json = $response->json();
        $this->assertEquals('The Password must be at least 6 characters.', $json['errors']['password'][0]);

        $payload['password'] = 'abc123';
        $response = $this->apiCall('PUT', route('api.users.update', $userId), $payload);
        $response->assertStatus(204);

        // It's OK to update a user without the password
        unset($payload['password']);
        $response = $this->apiCall('PUT', route('api.users.update', $userId), $payload);
        $response->assertStatus(204);
    }

    /**
     * Create and validate username
     */
    public function testCreateUserValidateUsername()
    {
        // Valid cases
        $usernames = [
            "admin",
            "john.doe",
            "heaney-esperanza",
            "jackeline53@rowe.com",
            "antonette06@yahoo.com",
            "metz.tierra@quigley.com",
            "roberts-kaitlin@gmail.com",
            "elise~reichert+1@gmail.com",
            "oleta#runolfsdottir@mertz.net",
            "simple@example.com",
            "very.common@example.com",
            "disposable.style.email.with+symbol@example.com",
            "other.email-with-hyphen@example.com",
            "fully-qualified-domain@example.com",
            // may go to user.name@example.com inbox depending on mail server
            "user.name+tag+sorting@example.com",
            // (one-letter local-part)
            "x@example.com",
            "example-indeed@strange-example.com",
            "example@s.example",
            // (space between the quotes)
            // (bangified host route used for uucp mailers)
            'mailhost!username@example.org',
            // (local part ending with non-alphanumeric character from the list of allowed printable characters)
            'user-@example.org',
            '123',
            'abc',
        ];

        $faker = Faker::create();
        $url = self::API_TEST_URL;
        foreach($usernames as $username) {
            $response = $this->apiCall('POST', $url, $data =[
                'username' => $username,
                'firstname' => $faker->firstName(),
                'lastname' => $faker->lastName(),
                'email' => $faker->email,
                'status' => $faker->randomElement(['ACTIVE', 'INACTIVE']),
                'password' => $faker->sentence(10)
            ]);
            //Validate the header status code
            $response->assertStatus(201);
        }

        // Invalid cases
        $usernames = [
            "12",
            "ab",
            "test/test@test.com",
            // (space between the quotes)
            '" "@example.org',
            // (quoted double dot)
            '"john..doe"@example.org',
            // (bangified host route used for uucp mailers)
            'mailhost!username@example.org',
            // (% escaped mail route to user@example.com via example.org)
            'user%example.com@example.org',
        ];

        $faker = Faker::create();
        $url = self::API_TEST_URL;
        foreach($usernames as $username) {
            $response = $this->apiCall('POST', $url, $data =[
                'username' => $username,
                'firstname' => $faker->firstName(),
                'lastname' => $faker->lastName(),
                'email' => $faker->email,
                'status' => $faker->randomElement(['ACTIVE', 'INACTIVE']),
                'password' => $faker->sentence(10)
            ]);
            //Validate the header status code
            $response->assertStatus(422);
        }
    }
}
