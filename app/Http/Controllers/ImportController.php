<?php

namespace App\Http\Controllers;

use App\Models\AccreditationBooking;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function importZip(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip',
        ]);

        $zip = new ZipArchive;
        $file = $request->file('zip_file');
        $zipPath = $file->getRealPath();

        $extractPath = storage_path('app/temp_import_' . time());
        mkdir($extractPath, 0777, true);

        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            return back()->with('error', 'ZIP file extraction failed.');
        }

        $excelPath = collect(scandir($extractPath))
            ->filter(fn($f) => Str::endsWith($f, ['.xlsx', '.xls']))
            ->map(fn($f) => $extractPath . '/' . $f)
            ->first();

        if (!$excelPath) {
            return back()->with('error', 'Excel file not found in ZIP.');
        }

        $spreadsheet = IOFactory::load($excelPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        unset($rows[1]);

        foreach ($rows as $row) {
            $name = $row['A'] ?? '';
            $email = $row['B'] ?? '';
            $photoFile = $row['C'] ?? '';
            $docFile = $row['D'] ?? '';
            $company = $row['E'] ?? '';
            $designation = $row['F'] ?? '';
            $organisation = $row['G'] ?? '';
            $number = $row['H'] ?? '';
            $password = $row['I'] ?? '123456';
            $reporting_user = $row['J'] ?? 'reporting_user';
            $ticket_id = $row['K'] ?? 'ticket_id';
            $discount = $row['L'] ?? '0';
            $payment_method = $row['M'] ?? 'null';
            $type = $row['N'] ?? 'daily';

            $user = new User();
            $user->name = $name;
            $user->email = $email;
            $user->company_name = $company;
            $user->designation = $designation;
            $user->organisation = $organisation;
            $user->number = $number;
            $user->password = Hash::make($password);
            $user->status = 1;
            
            $user->reporting_user = $reporting_user;

            if ($photoFile && file_exists($extractPath . '/photos/' . $photoFile)) {
                $photoPath = $this->storeFile($extractPath . '/photos/' . $photoFile, 'profile/' . str_replace(' ', '_', $name));
                $user->photo = $photoPath;
            }

            if ($docFile && file_exists($extractPath . '/docs/' . $docFile)) {
                $docPath = $this->storeFile($extractPath . '/docs/' . $docFile, 'document/' . str_replace(' ', '_', $name));
                $user->doc = $docPath;
            }

            $user->save();

            $booking = new AccreditationBooking();
            $booking->ticket_id = $ticket_id;
            $booking->user_id = $user->id;
            $booking->accreditation_id = Auth::user()->id ?? null;
            $booking->token = $this->generateHexadecimalCode();
            $ticket = Ticket::find($ticket_id);
            $booking->amount = $ticket ? $ticket->price : 0;
            $booking->email = $email;
            $booking->name = $name;
            $booking->number = $number;
            $booking->type = $type;
            $booking->payment_method = $payment_method;
            $booking->discount = $discount;
            $booking->status =  0 ;

            $booking->save();
        }

        \File::deleteDirectory($extractPath);

        // return back()->with('success', 'Users imported successfully.');
        return response()->json([
            'status' => 'success',
            'data' => 'Users imported successfully.',
           
        ]);
    }

    private function storeFile($sourcePath, $folder)
    {
        $destinationFolder = public_path('uploads/' . $folder);

        if (!file_exists($destinationFolder)) {
            mkdir($destinationFolder, 0777, true);
        }

        $filename = uniqid() . '_' . basename($sourcePath);
        $destinationPath = $destinationFolder . '/' . $filename;

        copy($sourcePath, $destinationPath);

        return url('uploads/' . $folder . '/' . $filename);
    }

    private function generateHexadecimalCode($length = 8)
    {
        $characters = '0123456789ABCDEF'; // Hexadecimal characters
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function mergeProfilePhotos()
    {
        $sourcePath = public_path('uploads/profile');
        $destinationPath = public_path('uploads/merged_photos');

        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0777, true);
        }

        $folders = File::directories($sourcePath);
        $copiedPhotos = [];

        foreach ($folders as $folderPath) {
            $folderName = basename($folderPath); // e.g., Abhishek

            $files = File::files($folderPath);

            foreach ($files as $file) {
                $ext = $file->getExtension();
                $newFileName = Str::slug($folderName, '_') . '.' . $ext;
                $newPath = $destinationPath . '/' . $newFileName;

                File::copy($file->getRealPath(), $newPath);

                $copiedPhotos[] = asset('uploads/merged_photos/' . $newFileName);
                break; // only 1 photo per user folder
            }
        }

        return response()->json([
            'status' => 'success',
            'total_copied' => count($copiedPhotos),
            'photos' => $copiedPhotos
        ]);
    }

    public function copyOriginalProfilePhotos()
    {
        $sourceBase = public_path('uploads/profile');
        $destination = public_path('uploads/all_original_photos');

        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0777, true);
        }

        $folders = File::directories($sourceBase);
        $copied = [];

        foreach ($folders as $folderPath) {
            $files = File::files($folderPath);

            foreach ($files as $file) {
                $originalName = $file->getFilename();
                $targetPath = $destination . '/' . $originalName;

                // If same name exists, skip or rename (optional logic here)
                if (!File::exists($targetPath)) {
                    File::copy($file->getRealPath(), $targetPath);
                    $copied[] = asset('uploads/all_original_photos/' . $originalName);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'total' => count($copied),
            'files' => $copied
        ]);
    }
}
