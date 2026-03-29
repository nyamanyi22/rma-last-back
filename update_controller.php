<?php

$filePath = 'c:\\Users\\Public\\Documents\\LIZRAMFRONT\\rma-last-back\\app\\Http\Controllers\\Api\\Admin\\CustomerController.php';
$content = file_get_contents($filePath);

$lastBracePos = strrpos($content, '}');

if ($lastBracePos !== false) {
    $newContent = substr($content, 0, $lastBracePos);
    
    $importMethod = '
    /**
     * Bulk import customers
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            \'customers\' => \'required|array\',
            \'customers.*.first_name\' => \'required|string|max:255\',
            \'customers.*.last_name\' => \'required|string|max:255\',
            \'customers.*.email\' => \'required|email\',
            \'customers.*.phone\' => \'nullable|string|max:20\',
            \'customers.*.country\' => \'nullable|string|size:2\',
            \'customers.*.city\' => \'nullable|string|max:255\',
            \'customers.*.address\' => \'nullable|string|max:500\',
            \'customers.*.postal_code\' => \'nullable|string|max:20\',
        ]);

        if ($validator->fails()) {
            return response()->json([
                \'success\' => false,
                \'errors\' => $validator->errors()
            ], 422);
        }

        $imported = [];
        $failed = [];
        $duplicates = [];

        foreach ($request->customers as $index => $customerData) {
            try {
                // Check if email already exists
                if (User::where(\'email\', $customerData[\'email\'])->exists()) {
                    $duplicates[] = [
                        \'row\' => $index + 1,
                        \'email\' => $customerData[\'email\'],
                        \'error\' => \'Email already exists\'
                    ];
                    continue;
                }

                $password = \'Welcome\' . rand(100, 999);

                $user = User::create([
                    \'first_name\' => $customerData[\'first_name\'],
                    \'last_name\' => $customerData[\'last_name\'],
                    \'email\' => $customerData[\'email\'],
                    \'phone\' => $customerData[\'phone\'] ?? null,
                    \'country\' => $customerData[\'country\'] ?? null,
                    \'city\' => $customerData[\'city\'] ?? null,
                    \'address\' => $customerData[\'address\'] ?? null,
                    \'postal_code\' => $customerData[\'postal_code\'] ?? null,
                    \'password\' => Hash::make($password),
                    \'role\' => UserRole::CUSTOMER,
                    \'is_active\' => true,
                ]);

                $imported[] = $user->id;
            } catch (Exception $e) {
                $failed[] = [
                    \'row\' => $index + 1,
                    \'email\' => $customerData[\'email\'] ?? \'Unknown\',
                    \'error\' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            \'success\' => true,
            \'message\' => "Imported " . count($imported) . " customers, " . count($failed) . " failed, " . count($duplicates) . " duplicates",
            '
            . '\'imported\' => $imported,
            \'failed\' => $failed,
            \'duplicates\' => $duplicates
        ]);
    }
}
';
    $newContent .= $importMethod;
    file_put_content($filePath, $newContent);
    echo "Successfully updated CustomerController.php\n";
} else {
    echo "Could not find last brace\n";
}
