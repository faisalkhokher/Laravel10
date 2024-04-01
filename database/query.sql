CREATE OR REPLACE FUNCTION insert_update_recurring_orders() RETURNS VOID AS $$
DECLARE
    v_row_count INT;
BEGIN
    -- Attempt to insert new rows while ignoring duplicates
    WITH ins AS (
        INSERT INTO recurring_orders (recurring_request_id, execution_date, attempts, progress, entity_id, created_at, updated_at)
        SELECT id, next_execution_on, 0, 'new', entity_id, now(), now()
        FROM recurring_requests rr
        WHERE rr.next_execution_on = date(now() AT TIME ZONE 'GMT' AT TIME ZONE 'Asia/Karachi')
        AND (rr.progress = 'new' OR rr.progress = 'ongoing')
        ORDER BY rr.id DESC
        ON CONFLICT (recurring_request_id, execution_date) DO NOTHING
        RETURNING *
    )
    SELECT COUNT(*) INTO v_row_count FROM ins;

    -- Use v_row_count as needed, for example, log it or return it.
    -- PostgreSQL does not have a direct ROW_COUNT() equivalent in this context,
    -- the row count is determined by the INSERT operation's returned rows.

    -- Update recurring_requests
    UPDATE recurring_requests rr
    SET last_execution_on = next_execution_on,
        next_execution_on = (rr.last_execution_on + INTERVAL '1 day')::date,
        progress = 'ongoing'
    WHERE rr.next_execution_on = date(now() AT TIME ZONE 'GMT' AT TIME ZONE 'Asia/Karachi')
    AND (rr.progress = 'new' OR rr.progress = 'ongoing');
END;
$$ LANGUAGE plpgsql;


--
use App\Models\RecurringOrder;
use App\Models\RecurringRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecurringOrderService
{
    public function insertAndUpdateOrders()
    {
        $now = Carbon::now();
        $karachiTime = $now->copy()->timezone('Asia/Karachi')->toDateString();

        // Select from recurring_requests
        $recurringRequests = RecurringRequest::whereDate('next_execution_on', $karachiTime)
            ->whereIn('progress', ['new', 'ongoing'])
            ->orderByDesc('id')
            ->get();

        foreach ($recurringRequests as $request) {
            // Attempt to insert, ignoring conflicts manually or via try-catch
            try {
                RecurringOrder::updateOrCreate(
                    [
                        'recurring_request_id' => $request->id,
                        'execution_date' => $request->next_execution_on,
                    ],
                    [
                        'attempts' => 0,
                        'progress' => 'new',
                        'entity_id' => $request->entity_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            } catch (\Exception $e) {
                // Handle exception or conflict here (log or ignore)
            }
        }

        // Update recurring_requests
        RecurringRequest::whereDate('next_execution_on', $karachiTime)
            ->whereIn('progress', ['new', 'ongoing'])
            ->update([
                'last_execution_on' => DB::raw('next_execution_on'),
                'next_execution_on' => DB::raw("DATE(next_execution_on + INTERVAL '1 day')"),
                'progress' => 'ongoing',
            ]);
    }
}
