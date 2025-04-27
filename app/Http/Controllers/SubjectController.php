<?php

namespace App\Http\Controllers;

use App\Models\subject;
use App\Models\user_to_payment;
use App\Models\user_to_payment_installment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubjectController extends Controller
{
  public function getAllSubject()
  {
    try {

      $subject = subject::all();
      return response()->json($subject);
    } catch (Exception $e) {
      return response()->json(['message' => $e->getMessage()], 400);
    }
  }

  public function getSubject($id)
  {
    try {
      $subject = DB::select("SELECT S.subject_grade,S.description,S.subject_name,S.subject_type,S.payment_duration,
                                S.rating,S.new_price,S.old_price,S.subject_image,S.subject_title FROM subjects S
                              WHERE subject_id = ?", [$id]);

      $subjectMatter = DB::select("SELECT S.matters FROM subject_matters S
                                  WHERE subject_id = ?", [$id]);

      $subjectMainContent = DB::select("SELECT S.main_title,S.time,S.count FROM subject_main_contents S
                            WHERE S.subject_id = ?", [$id]);
      return response()->json(["subject" => $subject, "subjectMatter" => $subjectMatter, "subjectMainContent" => $subjectMainContent]);
    } catch (Exception $e) {
      return response()->json(['message' => $e->getMessage()], 400);
    }
  }

  public function enrollCourse(Request $request)
  {
    try {

      $base64 = $request->slip;

      // Get file extension
      if (preg_match("/^data:([a-zA-Z0-9\/]+);base64,/", $base64, $matches)) {
          $mime = $matches[1]; // e.g. image/jpeg, application/pdf
          $extension = explode('/', $mime)[1]; // jpeg or pdf
      } else {
          return response()->json(['error' => 'Invalid file format'], 400);
      }

      // Remove base64 header
      $base64 = substr($base64, strpos($base64, ',') + 1);
      $base64 = base64_decode($base64);

      // Generate file name and save
      $fileName = uniqid() . '.' . $extension;
      $path = 'slips/' . $fileName;
      Storage::disk('public')->put($path, $base64);

      $payment  = new user_to_payment();
      $payment->user_id = $request->userId;
      $payment->name = $request->name;
      $payment->phone_number = $request->phone;
      $payment->subject_id = $request->subject_Id;
      $payment->payment_type = $request->paymentType;
      $payment->slip = $path;
      $payment->date = $request->date;


      if ($payment->save()) {
        if ($request->paymentType === 'installment') {
          $installment = new user_to_payment_installment();
          $installment->user_to_payment_id = $payment->user_to_payment_id;
          $installment->user_id = $request->userId;
          $installment->subject_id = $request->subject_Id;
          $installment->installment_type = $request->firstTInstallment;
        
        if ($installment->save()) {
          return response()->json(['message' => 'enroll success']);
        }else{
          return response()->json(['message' => 'enroll fail']);
        }
      }
        return response()->json(['message' => 'enroll success']);
      }
    } catch (Exception $e) {
      return response()->json(['message' => $e->getMessage()], 400);
    }
  }

}
