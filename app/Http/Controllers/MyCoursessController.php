<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MyCoursessController extends Controller
{
    public function my_courses($user_id)
    {
        try {

            $my_courses = DB::select(
                "SELECT US.user_id,US.subject_id,S.subject_name,S.rating,s.subject_image FROM user_to_subjects US
                            INNER JOIN subjects S ON S.subject_id = US.subject_id
                                        WHERE US.user_id = '$user_id' GROUP BY Us.subject_id"
               
            );

            return response()->json(["my_courses" => $my_courses]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function subject_main_content($user_id, $subject_id)
    {
        try {
            $my_courses = DB::select(
                "SELECT 
    SS.subject_sub_content_id,
    SS.subject_main_content_id,
		SMC.main_title,
    SS.sub_title,
    SS.time,
    IF(US.subject_main_content_id IS NOT NULL, SS.path, 'no') AS path
FROM 
    subject_sub_contents SS
		INNER JOIN subject_main_contents SMC ON SMC.subject_main_content_id = SS.subject_main_content_id
LEFT JOIN 
    user_to_subjects US 
    ON US.subject_main_content_id = SS.subject_main_content_id 
    AND US.user_id = '$user_id' 
    AND US.subject_id = '$subject_id'
WHERE 
    SS.subject_id = '$subject_id'"
               
            );


        // Transform the flat list into a nested array grouped by subject_main_content_id
        $courses = [];
        foreach ($my_courses as $row) {
            $main_content_id = $row->subject_main_content_id;

            // If this subject_main_content_id hasn't been added to $courses yet, create a new entry
            if (!isset($courses[$main_content_id])) {
                $courses[$main_content_id] = [
                    'main_title' => $row->main_title,
                    'lessons' => []
                ];
            }

            // Add the lesson to the lessons array for this subject_main_content_id
            $courses[$main_content_id]['lessons'][] = [
                'subject_sub_content_id' => $row->subject_sub_content_id,
                'sub_title' => $row->sub_title,
                'path' => $row->path,
                'time' => $row->time
            ];
        }

        // Reindex the array to remove the subject_main_content_id keys (convert to a sequential array)
        $courses = array_values($courses);

        return response()->json(["courses" => $courses]);
        // Return the transformed data as a JSON response (for an API)

            return response()->json($my_courses);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }


}
