<?php

namespace Modules\Document\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Models\Tenant\Document;
use Modules\Document\Http\Resources\DocumentNotSentCollection;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\Series;
use App\Models\Tenant\Person;
use App\Models\Tenant\StateType;
use App\Models\Tenant\Catalogs\DetractionType;
use App\Models\Tenant\Catalogs\PaymentMethodType as CatPaymentMethodType;
use App\Traits\OfflineTrait;

class DocumentController extends Controller
{
    use OfflineTrait;
    
    public function index()
    {

        $is_client = $this->getIsClient();

        return view('document::documents.not_sent', compact('is_client'));
    }

    public function records(Request $request)
    {
        
        $records = $this->getRecords($request);

        return new DocumentNotSentCollection($records->paginate(config('tenant.items_per_page')));

    }

    public function getRecords($request){


        $d_end = $request->d_end;
        $d_start = $request->d_start;
        $date_of_issue = $request->date_of_issue;
        $document_type_id = $request->document_type_id;
        $number = $request->number;
        $series = $request->series;
        $state_type_id = $request->state_type_id;
        $pending_payment = ($request->pending_payment == "true") ? true:false;
        $customer_id = $request->customer_id;
 

        if($d_start && $d_end){

            $records = Document::where('document_type_id', 'like', '%' . $document_type_id . '%')
                            ->where('series', 'like', '%' . $series . '%')
                            ->where('number', 'like', '%' . $number . '%')
                            ->where('state_type_id', 'like', '%' . $state_type_id . '%')
                            ->whereBetween('date_of_issue', [$d_start , $d_end])
                            ->whereNotSent()
                            ->whereTypeUser()
                            ->latest();

        }else{

            $records = Document::where('date_of_issue', 'like', '%' . $date_of_issue . '%')
                            ->where('document_type_id', 'like', '%' . $document_type_id . '%')
                            ->where('state_type_id', 'like', '%' . $state_type_id . '%')
                            ->where('series', 'like', '%' . $series . '%')
                            ->where('number', 'like', '%' . $number . '%')
                            ->whereNotSent()
                            ->whereTypeUser()
                            ->latest();
        }        

        if($pending_payment){ 
            $records = $records->where('total_canceled', false);
        }
        
        if($customer_id){
            $records = $records->where('customer_id', $customer_id);
        }

        return $records;

    }

    public function data_table()
    {
        
        $customers = Person::whereType('customers')->orderBy('name')->take(20)->get()->transform(function($row) {
            return [
                'id' => $row->id,
                'description' => $row->number.' - '.$row->name,
                'name' => $row->name,
                'number' => $row->number,
                'identity_document_type_id' => $row->identity_document_type_id,
            ];
        });

        $document_types = DocumentType::whereIn('id', ['01', '03','07', '08'])->get();
        $series = Series::whereIn('document_type_id', ['01', '03','07', '08'])->get();
        $establishments = Establishment::where('id', auth()->user()->establishment_id)->get(); 
        $state_types = StateType::get();
                       
        return compact( 'customers', 'document_types','series','establishments', 'state_types');

    }


    
    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {
            $new_request = [
                'file' => $request->file('file'),
                'type' => $request->input('type'),
            ];

            return $this->upload_image($new_request);
        }
        return [
            'success' => false,
            'message' =>  __('app.actions.upload.error'),
        ];
    }

    function upload_image($request)
    {
        $file = $request['file'];
        $type = $request['type'];

        $temp = tempnam(sys_get_temp_dir(), $type);
        file_put_contents($temp, file_get_contents($file));

        $mime = mime_content_type($temp);
        $data = file_get_contents($temp);

        return [
            'success' => true,
            'data' => [
                'filename' => $file->getClientOriginalName(),
                'temp_path' => $temp,
                'temp_image' => 'data:' . $mime . ';base64,' . base64_encode($data)
            ]
        ];
    }

    
    public function detractionTables()
    {
        
        $cat_payment_method_types = CatPaymentMethodType::whereActive()->get();
        $detraction_types = DetractionType::whereActive()->get();
                       
        return compact( 'detraction_types', 'cat_payment_method_types');

    }


    public function dataTableCustomers(Request $request)
    {


        $customers = Person::where('number','like', "%{$request->input}%")
                            ->orWhere('name','like', "%{$request->input}%")
                            ->whereType('customers')->orderBy('name')
                            ->get()->transform(function($row) {
                                return [
                                    'id' => $row->id,
                                    'description' => $row->number.' - '.$row->name,
                                    'name' => $row->name,
                                    'number' => $row->number,
                                    'identity_document_type_id' => $row->identity_document_type_id,
                                ];
                            });

        return compact('customers');
    }
}
