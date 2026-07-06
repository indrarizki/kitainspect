<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\AttendanceSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TelegramController extends Controller
{
     public function webhook(Request $request)
    {
        $data = $request->all();

        if (!isset($data['message'])) {
            return response()->json(['status' => 'ok']);
        }

        $chatId = $data['message']['chat']['id'];
        $text = trim($data['message']['text'] ?? '');

        if ($text == '/start') {
            $this->sendMessage(
                $chatId,
                "Selamat datang.\nSilakan masukkan NIK Anda."
            );

            return response()->json(['status' => 'ok']);
        }

        $employee = Employee::where('code', $text)->first();

        if ($employee) {

            $employee->telegram_chat_id = $chatId;
            $employee->save();

            $this->sendMessage(
                $chatId,
                "Akun Telegram berhasil terhubung."
            );
        }
        else {

            $this->sendMessage(
                $chatId,
                "NIK tidak ditemukan."
            );
        }

        return response()->json(['status' => 'ok']);
    }

    public function telegramTest()
    {
        $employee = Employee::find(1);

        Http::post(
            'https://api.telegram.org/bot'.env('TELEGRAM_BOT_TOKEN').'/sendMessage',
            [
                'chat_id' => $employee->telegram_chat_id,
                'text' => 'Tes notifikasi HRIS'
            ]
        );

        return 'OK';    
    }

    private function sendMessage($chatId, $message)
    {
        file_get_contents(
            "https://api.telegram.org/bot".config('services.telegram.token')."/sendMessage?" .
            http_build_query([
                'chat_id' => $chatId,
                'text' => $message
            ])
        );
        
    }

    public function register(Request $request)
    {
        $employee = Employee::where(
            'code',
            $request->employee_code
        )->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'NIK tidak ditemukan'
            ], 404);
        }

        $employee->telegram_chat_id = $request->chat_id;
        $employee->save();

        return response()->json([
            'success' => true,
            'message' => 'Akun Telegram berhasil terhubung',
            'employee' => $employee->fullName
        ]);
    }



    public function telegramSlip(Request $request)
    {
        $employee = Employee::where('code',$request->nik)
        ->where('dateOfBirth', $request->birthdate)
        ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'NIK / Tanggal lahir tidak ditemukan'
            ], 404);
        }

        [$year, $month] = explode('-', $request->month);

        $rows = DB::table('payrolls as p')
            ->join('employees as e', 'p.employee_id', '=', 'e.id')
            ->join('payroll_periods as pp', 'p.period_id', '=', 'pp.id')
            ->join('payroll_details as pd', 'p.id', '=', 'pd.payroll_id')
            ->join('salary_components as sc', 'sc.id', '=', 'pd.component_id')
            ->where('p.employee_id', $employee->id)
            ->where('pp.month', (int)$month)
            ->where('pp.year', (int)$year)
            ->select(
                'p.takeHomePay',
                'e.fullName',
                'e.code',
                'pp.month',
                'pp.year',
                'pd.benefitValue',
                'sc.name',
                'sc.state'
            )
            ->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Slip tidak ditemukan'
            ], 404);
        }

        $first = $rows->first();

        $attendanceSummary = AttendanceSummary::where('employee_id', $employee->id)
            ->where('month', (int) $month)
            ->where('year', (int) $year)
            ->first();

        $earnings = [];
        $deductions = [];
        $adjustments = [];

        foreach ($rows as $row) {

            $item = [
                'name' => $row->name,
                'value' => (float) $row->benefitValue
            ];

            switch ($row->state) {

                case 'E':
                    $earnings[] = $item;
                    break;

                case 'D':
                    $deductions[] = $item;
                    break;

                case 'A':
                    $adjustments[] = $item;
                    break;
            }
        }

        return response()->json([
            'success' => true,

            'employee' => [
                'name' => $first->fullName,
                'code' => $first->code
            ],

            'period' => [
                'month' => $first->month,
                'year' => $first->year
            ],

            'take_home_pay' => (float) $first->takeHomePay,
            'totalWorkday' => $attendanceSummary?->totalWorkday,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'adjustments' => $adjustments
        ]);
    }


}
