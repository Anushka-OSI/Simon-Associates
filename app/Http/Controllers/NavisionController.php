<?php

namespace App\Http\Controllers;

use App\Models\CompanyDetails;
use App\Models\Invoice;
use App\Models\InvoiceDetails;
use App\Models\handler;
use App\Models\payment;
use App\Models\User;
use App\Models\Modelreceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class NavisionController extends Controller
{
    public function dashboard()
    {
        $data = Invoice::orderByDesc('id')->paginate(10);

        $sa = [2, 3, 4, 5];
        $apr_cnt = Invoice::where('status', 6)->count();
        $out_cnt = Invoice::where('status', 7)->count();
        $ong_cnt = Invoice::whereIN('status', $sa)->count();

        $recent = Invoice::where('status', 1)->get();
        return view('User1.home', compact('data', 'apr_cnt', 'out_cnt', 'ong_cnt', 'apr_cnt', 'recent'));
    }

    public function CompanyRegister()
    {
        $handlers = handler::all();

        return view('User1.Registration', compact('handlers'));
    }

    public function ongoingInvoice()
    {
        $data = Invoice::where('status', '2')->orWhere('status', '3')->orWhere('status', '4')->orWhere('status', '5')->get();

        return view('User1.ongoing', compact('data'));
    }

    public function ApprovedInvoice()
    {
        $data = Invoice::where('status', '6')->get();

        return view('User1.Approved', compact('data'));
    }

    public function OutstandingInvoice()
    {
        $data = Invoice::where('status', '7')->get();

        foreach ($data as $get) {
            $count = DB::table('invoice_details')
                ->where('invoiceNumber', $get->invoiceNumber)
                ->where('status', 0)
                ->get();

            if (count($count) == 0) {
                $get->status = 8;
                $get->save();
            }
        }

        $data = Invoice::where('status', '7')->get();

        $company = CompanyDetails::all();

        return view('User1.Outstanding', compact('data', 'company'));
    }
    public function Receipt()
    {

        $data = Modelreceipt::orderByDesc('id')->paginate(30);
        $ComData = Invoice::all();

        return view('User1.Receipt', compact('data', 'ComData'));
    }

    public function dashboardUserTwo()
    {
        $apr_cnt = Invoice::where('status', 7)->count();
        return view('User2.Home', compact('apr_cnt'));
    }

    public function modify($invoiceNumber)
    {

        $invoiceNumber = str_replace('-', '/', $invoiceNumber);
        $invoice = Invoice::where('invoiceNumber', $invoiceNumber)->first();
        $invoice_data = InvoiceDetails::where('invoiceNumber', $invoiceNumber)->get();

        $bank = payment::all();

        return view('User1.editInvoicer', compact('invoice', 'invoice_data', 'invoiceNumber', 'bank'));
    }

    public function updateForm()
    {
        $data = payment::all();
        $userData = User::all();
        $handlerData = handler::all();
        $customersList = CompanyDetails::all();


        return view('User2.update-form', compact('data', 'userData', 'handlerData', 'customersList'));
    }

    // ===========================================================

    public function user2()
    {
        $data = Invoice::orderByDesc('id')->paginate(10);

        $sa = [2, 3, 4, 5];
        $apr_cnt = Invoice::where('status', 6)->count();
        $out_cnt = Invoice::where('status', 7)->count();
        $ong_cnt = Invoice::whereIN('status', $sa)->count();

        $invoices = Invoice::latest()->take(10)->get();

        $amount = [];

        foreach ($invoices as $invoice) {
            $total = 0; // Reset total for each invoice
            $subInvoice = InvoiceDetails::where('invoiceNumber', $invoice->invoiceNumber)->get();

            foreach ($subInvoice as $detail) {
                $total = 0; // Initialize total outside the loop
                foreach ($subInvoice as $detail) {
                    if ($detail->discount != 0) {
                        $totalT = $detail->price * $detail->dollerRate;
                        $totalT -= ($totalT * $detail->discount) / 100;
                        // $totalT = $totalT/$detail->discount;
                    } else {
                        $totalT = $detail->price * $detail->dollerRate;
                    }
                    if ($detail->convertToD == 1) {
                        $totalT = $detail->price * $detail->dollerRate;
                    }
                    $total += $totalT;

                    // $total += $detail->price * $detail->dollerRate - ($detail->discount != 0 ? $total / $detail->discount : 0);
                }
            }

            $amount[$invoice->id] = $total;
        }
        $amount = array_reverse($amount, true);

        return view('User2.home', compact('data', 'apr_cnt', 'out_cnt', 'ong_cnt', 'apr_cnt', 'invoices', 'amount'));
    }

    public function viewUserTwo($invoiceNumber)
    {
        $invoiceNumberModify = str_replace('-', '/', $invoiceNumber);
        $invoice_data = InvoiceDetails::where('invoiceNumber', $invoiceNumberModify)->get();
        $invoice = Invoice::where('invoiceNumber', $invoiceNumberModify)->first();

        $bankAccount = payment::where('id', $invoice->bankId)->first();

        return view('User2.generateInvoice', compact('invoice_data', 'invoiceNumber', 'invoice', 'bankAccount'));
    }

    public function sendUsertree($invoiceNumber)
    {
        $invoiceNumberModify = str_replace('-', '/', $invoiceNumber);
        $data = Invoice::where('invoiceNumber', $invoiceNumberModify)->first();
        $data->status = '3';
        $data->save();

        return redirect()->route('new-invoice-user')->with('good', 'Invoice successfully sent to the approver.');
    }

    public function sendUserOne($invoiceNumber)
    {
        $invoiceNumberModify = str_replace('-', '/', $invoiceNumber);
        $data = Invoice::where('invoiceNumber', $invoiceNumberModify)->first();
        $data->status = '5';
        $data->save();

        return redirect()->route('new-invoice-user')->with('good', 'Invoice successfully sent to the approver.');
    }

    // ===========================================================

    public function generateInvoice($id)
    {
        $data = CompanyDetails::findOrFail($id);
        $handler = handler::where('id', $data->handleBy)->first();

        $invoice = new Invoice();

        $invoice->to = $data->to;
        $invoice->email = $data->email;
        $invoice->companyName = $data->companyName;
        $invoice->address = $data->address;
        $invoice->status = '1';
        $invoice->handleBy = $handler->id;
        $invoice->refID = $data->id;

        $lastRow = Invoice::latest()->first();
        $lastId = $lastRow ? $lastRow->id + 1 : 1;

        $invoid = str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $currentMonth = date('n');
        $currentYear = date('Y');

        if ($currentMonth < 4) {
            $financialYear = substr($currentYear - 1, -2);
        } else {
            $financialYear = substr($currentYear, -2);
        }
        $customerName = $data->companyName;
        $customerInitial = strtoupper(substr($customerName, 0, 1));

        $invoiceNumber = "Sec/{$financialYear}/{$customerInitial}/{$invoid}";

        $invoice->invoiceNumber = $invoiceNumber;

        $invoice->save();

        $invoiceNumber = str_replace('/', '-', $invoiceNumber);

        return redirect()->route('invoiceGenForm', ['invoiceNumber' => $invoiceNumber]);
    }
}
