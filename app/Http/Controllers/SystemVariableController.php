<?php

namespace App\Http\Controllers;

use App\Models\SystemVariable;
use Illuminate\Http\Request;

class SystemVariableController extends Controller
{
    public function index()
    {
        $systemData = SystemVariable::all();

        return response()->json(['status' => true,  'message' => 'systemData  successfully!', 'systemData' => $systemData], 200);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'key' => 'required|string|unique:system_variables,key',
                'value' => 'required|string|unique:system_variables,value',
            ]);

            $systemVariable = new SystemVariable();

            $systemVariable->key = $request->input('key');
            $systemVariable->value = $request->input('value');

            $systemVariable->save();

            return response()->json([
                'status' => true,
                'message' => 'System Variable created successfully!',
                'data' => $systemVariable
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors();
            $message = '';

            if ($errors->has('key')) {
                $message = 'The key has already been taken.';
            }

            if ($errors->has('value')) {
                $message = 'The value has already been taken.';
            }

            if ($errors->has('key') && $errors->has('value')) {
                $message = 'Both key and value have already been taken.';
            }

            return response()->json([
                'status' => false,
                'message' => $message
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'error' => 'Failed to save system variable.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $systemVariableData = SystemVariable::findOrFail($id);

            $request->validate([
                'key' => 'required|string|unique:system_variables,key,' . $id,
                'value' => 'required|string|unique:system_variables,value,' . $id,
            ]);

            $systemVariableData->key = $request->input('key');
            $systemVariableData->value = $request->input('value');
            $systemVariableData->save();

            return response()->json([
                'status' => true,
                'message' => 'System Variable updated successfully!',
                'data' => $systemVariableData
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors();
            $message = '';

            if ($errors->has('key')) {
                $message = 'The key has already been taken.';
            }

            if ($errors->has('value')) {
                $message = 'The value has already been taken.';
            }

            if ($errors->has('key') && $errors->has('value')) {
                $message = 'Both key and value have already been taken.';
            }

            return response()->json([
                'status' => false,
                'message' => $message
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update system variable.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $systemVariableData = SystemVariable::findOrFail($id);

            $systemVariableData->delete();

            return response()->json(['status' => true, 'message' => 'System Variable deleted successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to delete configuration', 'error' => $e->getMessage()], 500);
        }
    }
}
