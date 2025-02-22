<?php

namespace Tests\Unit;

use App\Models\User;
use ReflectionClass;
use Illuminate\Http\Request;
use App\Http\Enums\SlotSymbols;
use App\Http\Services\SlotService;
use Illuminate\Support\Facades\Hash;
use App\Http\Repositories\UserRepository;
use Illuminate\Foundation\Testing\TestCase;
use App\Http\Repositories\GameSessionsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SlotServiceTest extends TestCase
{
    use RefreshDatabase;

    private $slotService;
    private $slotServiceMock;
    private $mockSessionsRepository;
    private $mockUserRepository;

    private $user;
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Roel Sax',
            'email' => 'roelsax@gmail.com',
            'password' => Hash::make('paswoord')
        ]);

        $this->sessionsRepository = new GameSessionsRepository();
        $this->mockUserRepository = $this->createMock(UserRepository::class);
        $this->slotService = new SlotService($this->sessionsRepository, $this->mockUserRepository);
    }

    public function testStartSession()
    {   
        $this->be($this->user);

        $result = $this->postJson('/api/start-session', []);;
        
        $this->assertEquals($this->user->id, $result->getData()->user_id);
        $this->assertEquals(true, $result->getData()->active);
        $this->assertEquals(false, $result->getData()->cashed_out);
        $this->assertEquals(10, $result->getData()->current_game_credit);
        $result->assertStatus(201);
    }

    public function testStartNotAuthenticatedSession()
    {
        $result = $this->postJson('/api/start-session', []);

        $this->assertEquals('User not authenticated', $result->getData()->error);
        $result->assertStatus(401);
    }

    public function testStartSessionAlreadyExists() 
    {
        $this->be($this->user);
        
        $result1 = $this->postJson('/api/start-session', []);
        $result2 = $this->postJson('/api/start-session', []);

        $this->assertEquals($this->user->id, $result1->getData()->user_id);
        $this->assertEquals($result2->getData()->guid, $result1->getData()->guid);
        $this->assertEquals($result2->getData()->current_game_credit, $result1->getData()->current_game_credit);
        $result2->assertStatus(201);
    }

    public function testRoll()
    {
        $this->be($this->user);
        $resultStart = $this->postJson('/api/start-session', []);
        $guid = $resultStart->getData()->guid;
        $credits = $resultStart->getData()->current_game_credit;
        $request = new Request(['currentCredits' => 10, 'active' => true, 'cashed_out' => false, 'guid' => $guid]);

        $resultStartGame = $this->postJson('/api/start-game', $request->all());

        $this->assertNotEquals($credits, $resultStartGame->getData()->credits);
        $this->assertEquals($this->user->last_game_session, $guid);
        $resultStartGame->assertStatus(200);
    }

    public function testRollNotAuthenticated() 
    {
        $request = new Request([
            'currentCredits' => 10, 
            'active' => true, 
            'cashed_out' => false, 
            'guid' => 'ID28U3-KJHIU32'
        ]);

        $resultStartGame = $this->postJson('/api/start-game', $request->all());

        $this->assertEquals('User not authenticated', $resultStartGame->getData()->error);
        $resultStartGame->assertStatus(401);
    }

    public function testCashOut()
    {
        $this->be($this->user);
        $resultStart = $this->postJson('/api/start-session', []);
        $guid = $resultStart->getData()->guid;
        $credit_total = $this->user->credit_total;
        $cashed_out = 55;
        $request = new Request([
            'currentCredits' => $cashed_out, 
            'active' => false, 
            'cashed_out' => true, 
            'cashed_out_amount' => $cashed_out,
            'guid' => $guid
        ]);

        $result = $this->postJson('/api/' . $guid . '/update-session/', $request->all());
        
        $this->assertEquals($this->user->last_game_session, $guid);
        $this->assertEquals($this->user->credit_total, $credit_total + $cashed_out);
        $this->assertEquals(false, $result->getData()->active);
        $this->assertEquals(true, $result->getData()->cashed_out);
        $this->assertEquals($cashed_out, $result->getData()->current_game_credit);
        $this->assertEquals($cashed_out, $result->getData()->cashed_out_amount);
        $result->assertStatus(200);
    }

    public function testCashOutWithoutSession()
    {
        $this->be($this->user);
        $request = new Request([
            'currentCredits' => 55, 
            'active' => false, 
            'cashed_out' => true, 
            'cashed_out_amount' => 55,
            'guid' => 'ID28U3-KJHIU32'
        ]);

        $result = $this->postJson('/api/ID28U3-KJHIU32/update-session/', $request->all());
        $this->assertEquals('Session not found', $result->getData()->error);
        $result->assertStatus(404);
    }

    public function testCashOutNotAuthenticated()
    {
        $cashed_out = 55;
        $request = new Request([
            'currentCredits' => $cashed_out, 
            'active' => false, 
            'cashed_out' => true, 
            'cashed_out_amount' => $cashed_out,
            'guid' => 'ID28U3-KJHIU32'
        ]);

        $result = $this->postJson('/api/ID28U3-KJHIU32/update-session/', $request->all());
        
        $this->assertEquals('User not authenticated', $result->getData()->error);
        $result->assertStatus(401);
    }

    public function testCreditsHitZero() 
    {
        $this->be($this->user);
        $resultStart = $this->postJson('/api/start-session', []);
        $guid = $resultStart->getData()->guid;
        $credit_total = $this->user->credit_total;
        $cashed_out = 0;

        $request = new Request([
            'currentCredits' => $cashed_out, 
            'active' => false, 
            'cashed_out' => false, 
            'cashed_out_amount' => $cashed_out,
            'guid' => $guid
        ]);

        $result = $this->postJson('/api/' . $guid . '/update-session/', $request->all());

        $this->assertEquals($this->user->last_game_session, $guid);
        $this->assertEquals($this->user->credit_total, $credit_total + $cashed_out);
        $this->assertEquals(false, $result->getData()->active);
        $this->assertEquals(false, $result->getData()->cashed_out);
        $this->assertEquals($cashed_out, $result->getData()->current_game_credit);
        $this->assertEquals($cashed_out, $result->getData()->cashed_out_amount);
        $result->assertStatus(200);
    }

    public function testSessionEndedByLogout()
    {
        $this->be($this->user);
        $resultStart = $this->postJson('/api/start-session', []);
        $guid = $resultStart->getData()->guid;
        $credit_total = $this->user->credit_total;
        

        $request = new Request([
            'currentCredits' => 17, 
            'active' => false, 
            'cashed_out' => false, 
            'cashed_out_amount' => 0,
            'guid' => $guid
        ]);

        $result = $this->postJson('/api/' . $guid . '/update-session/', $request->all());

        $this->assertEquals($this->user->last_game_session, $guid);
        $this->assertEquals($this->user->credit_total, $credit_total + $result->getData()->cashed_out_amount);
        $this->assertEquals(false, $result->getData()->active);
        $this->assertEquals(false, $result->getData()->cashed_out);
        $this->assertEquals(17, $result->getData()->current_game_credit);
        $this->assertEquals(0, $result->getData()->cashed_out_amount);
        $result->assertStatus(200);
    }

    public function testCreditsBelow40() 
    {
        $this->be($this->user);
        
        $request = new Request([
            'currentCredits' => 30,
            'guid' => 'test-guid'
        ]);

        $response = $this->slotService->rollSlot($request);

        $this->assertEquals(200, $response->status());
        $this->assertIsArray($response->getData(true));
    }

    public function testCreditsBetween40And60() 
    {
        $this->be($this->user);
        
        $request = new Request([
            'currentCredits' => 50,
            'guid' => 'test-guid'
        ]);

        $response = $this->slotService->rollSlot($request);

        $this->assertEquals(200, $response->status());
        $this->assertIsArray($response->getData(true));
    }

    public function testCreditsAbove60() 
    {
        $this->be($this->user);
        
        $request = new Request([
            'currentCredits' => 70,
            'guid' => 'test-guid'
        ]);

        $response = $this->slotService->rollSlot($request);

        $this->assertEquals(200, $response->status());
        $this->assertIsArray($response->getData(true));
    }

    public function testNegativeCredits() 
    {
        $this->be($this->user);
        
        $request = new Request([
            'currentCredits' => -20,
            'guid' => 'test-guid'
        ]);

        $response = $this->slotService->rollSlot($request);

        $this->assertEquals(422, $response->status());
        $this->assertEquals('Incorrect amount of credits', $response->getData()->error);
    }

    public function testCheckIfWinningRoll()
    {
        $roll = [SlotSymbols::Cherry, SlotSymbols::Cherry, SlotSymbols::Cherry];
        $result = $this->invokePrivateMethod($this->slotService, 'checkIfWinningRoll', [$roll]);

        $this->assertInstanceOf(SlotSymbols::class, $result);
        $this->assertEquals(SlotSymbols::Cherry, $result);
    }

    public function testCheckIfLosingRoll() 
    {
        $roll = [SlotSymbols::Cherry, SlotSymbols::Cherry, SlotSymbols::Watermelon];
        $result = $this->invokePrivateMethod($this->slotService, 'checkIfWinningRoll', [$roll]);
        
        $this->assertEquals(false, $result);
    }

    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function getMockedRoll($rollOutcome) {
        $this->slotServiceMock->method('roll')->willReturn($rollOutcome);
    }
}
