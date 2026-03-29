import os

file_path = r'c:\Users\Public\Documents\LIZRAMFRONT\rma-last-back\app\Http\Controllers\Api\Admin\CustomerController.php'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Find the last closing brace
last_brace_index = content.rfind('}')
if last_brace_index != -1:
    new_content = content[:last_brace_index]
    
    import_method = """
    /**
     * Bulk import customers
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customers' => 'required|array',
            'customers.*.first_name' => 'required|string|max:255',
            'customers.*.last_name' => 'required|string|max:255',
            'customers.*.email' => 'required|email',
            'customers.*.phone' => 'nullable|string|max:20',
            'customers.*.country' => 'nullable|string|size:2',
            'customers.*.city' => 'nullable|string|max:255',
            'customers.*.address' => 'nullable|string|max:500',
            'customers.*.postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imported = [];
        $failed = [];
        $duplicates = [];

        foreach ($request->customers as $index => $customerData) {
            try {
                // Check if email already exists
                if (User::where('email', $customerData['email'])->exists()) {
                    $duplicates[] = [
                        'row' => $index + 1,
                        'email' => $customerData['email'],
                        'error' => 'Email already exists'
                    ];
                    continue;
                }

                $password = 'Welcome' . rand(100, 999);

                $user = User::create([
                    'first_name' => $customerData['first_name'],
                    'last_name' => $customerData['last_name'],
                    'email' => $customerData['email'],
                    'phone' => $customerData['phone'] ?? null,
                    'country' => $customerData['country'] ?? null,
                    'city' => $customerData['city'] ?? null,
                    'address' => $customerData['address'] ?? null,
                    'postal_code' => $customerData['postal_code'] ?? null,
                    'password' => Hash::make($password),
                    'role' => UserRole::CUSTOMER,
                    'is_active' => true,
                ]);

                $imported[] = $user->id;
            } catch (\\Exception $e) {
                $failed[] = [
                    'row' => $index + 1,
                    'email' => $customerData['email'] ?? 'Unknown',
                    'error' => $e.getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Imported " . count($imported) . " customers, " . count($failed) . " failed, " . count($duplicates) . " duplicates",
            'imported' => $imported,
            'failed' => $failed,
            'duplicates' => $duplicates
        ]);
    }
}
"""
    new_content += import_method
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Successfully updated CustomerController.php")
else:
    print("Could not find last brace")
