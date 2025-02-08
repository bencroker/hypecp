<?php

namespace App\Http\Controllers;

use App\Models\Datastar\Chat;
use Illuminate\Http\Request;
use App\Models\Datastar\Room;
use App\Models\Datastar\Member;
use Illuminate\Support\Str;
use Putyourlightson\Datastar\DatastarEventStream;
use starfederation\datastar\ServerSentEventGenerator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WaveController extends Controller
{
    use DatastarEventStream;

    public function createRoom()
    {
        $room = new Room;
        $room->name = request('room_name');
        $room->code = strtoupper(Str::random(6));
        $room->save();
        return redirect('wave/'.$room->code);
    }
    public function createMember()
    {
        $room = Room::find(request('room_id'));
        $member = new Member;
        $member->room_id = $room->id;
        $member->name = request('member_name');
        $member->save();

        session([$room->code => ['member_id' => $member->id]]);

        return redirect('wave/'.$room->code);
    }

    public function room($code)
    {
        $room = Room::where('code', $code)
                    ->firstOrFail();

        return view('wave.main', compact('room'));
    }

    public function updater(): StreamedResponse
    {
        return $this->getStreamedResponse(function() {
            $signals = $this->getSignals();
            $room = Room::find($signals->room_id);

            // browser was killing
            ini_set('max_execution_time', 36000);

            $last_updated = round(microtime(true) * 1000);
            $sleeptime = 50 * 1000; // 1000 * milliseconds

            while (true) {
                $updates = Chat::where('room_id', $room->id)
                    ->where('updated_milliseconds', '>', $last_updated)
                    ->withTrashed()
                    ->count();

                if ($updates > 0 || $last_updated < round(microtime(true) * 1000) - 20000) {
                    $this->mergeFragments(
                        view('wave.room', ['room' => $room])->render()
                    );

                    $last_updated = round(microtime(true) * 1000);

                    $this->executeScript('scrollToBottom()');
                }

                usleep($sleeptime);
            }
        });
    }
}
