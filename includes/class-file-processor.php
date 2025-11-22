<?php
/**
 * File processing class for Alumni Bulk Email plugin
 * 
 * Handles CSV/Excel import/export functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Alumni_File_Processor {
    
    /**
     * Parse a file (auto-detect CSV or Excel)
     */
    public function parse_file($file_path, $file_type = null) {
        // Determine file type if not provided
        if (!$file_type) {
            $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            if (in_array($extension, ['xlsx', 'xls'])) {
                $file_type = 'excel';
            } else {
                $file_type = 'csv';
            }
        }
        
        if ($file_type === 'excel') {
            return $this->parse_excel_file($file_path);
        } else {
            return $this->parse_csv_file($file_path);
        }
    }
    
    /**
     * Parse CSV file
     */
    private function parse_csv_file($file_path) {
        $recipients = array();
        
        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",", '"', "\\");
            if (!$header) {
                fclose($handle);
                return $recipients;
            }
            
            // Debug logging
            error_log('Alumni Bulk Email - CSV Header: ' . print_r($header, true));
            
            // Clean up header names - just store all columns as-is
            $cleaned_headers = array();
            
            foreach ($header as $index => $column) {
                $cleaned_header = trim($column);
                $cleaned_headers[$index] = $cleaned_header;
            }
            
            error_log('Alumni Bulk Email - CSV columns detected: ' . print_r($cleaned_headers, true));
            
            $row_count = 0;
            $valid_recipients = 0;
            while (($data = fgetcsv($handle, 1000, ",", '"', "\\")) !== FALSE) {
                $row_count++;
                
                // Debug log each row
                error_log('Alumni Bulk Email - Row ' . $row_count . ': ' . print_r($data, true));
                
                // Allow rows with fewer columns - they might just have empty trailing fields
                if (count($data) == 0 || (count($data) == 1 && trim($data[0]) == '')) {
                    // Skip completely empty rows
                    error_log('Alumni Bulk Email - Skipping empty row ' . $row_count);
                    continue;
                }
                
                // Store all rows regardless of email validation
                $valid_recipients++;
                $recipient = array();
                
                // Store all columns dynamically
                foreach ($cleaned_headers as $index => $column_name) {
                    $value = isset($data[$index]) ? trim($data[$index]) : '';
                    $recipient[$column_name] = $value;
                }
                
                // Add tags column (initially empty for all recipients)
                $recipient['tags'] = '';
                
                // Generate standard fields for backward compatibility (only if we can extract them)
                $recipient['name'] = $this->extract_name_field($recipient);
                $recipient['first_name'] = $this->extract_first_name($recipient);
                $recipient['last_name'] = $this->extract_last_name($recipient);
                
                $recipients[] = $recipient;
            }
            fclose($handle);
            
            error_log('Alumni Bulk Email - CSV parsing complete. Total rows: ' . $row_count . ', Valid recipients: ' . $valid_recipients);
        }
        
        return $recipients;
    }
    
    /**
     * Parse Excel file
     */
    private function parse_excel_file($file_path) {
        $recipients = array();
        
        try {
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                error_log('Alumni Bulk Email - PHPSpreadsheet not available');
                throw new Exception('Excel processing library not available');
            }
            
            // Load the Excel file
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (empty($rows)) {
                error_log('Alumni Bulk Email - Excel file is empty');
                return $recipients;
            }
            
            // First row is the header
            $header = array_shift($rows);
            if (!$header) {
                error_log('Alumni Bulk Email - Excel file has no header');
                return $recipients;
            }
            
            // Debug logging
            error_log('Alumni Bulk Email - Excel Header: ' . print_r($header, true));
            
            // Clean up header names - just store all columns as-is
            $cleaned_headers = array();
            foreach ($header as $index => $column) {
                $cleaned_header = trim($column);
                $cleaned_headers[$index] = $cleaned_header;
            }
            
            error_log('Alumni Bulk Email - Excel columns detected: ' . print_r($cleaned_headers, true));
            
            $row_count = 0;
            $valid_recipients = 0;
            
            foreach ($rows as $data) {
                $row_count++;
                
                // Debug log each row
                error_log('Alumni Bulk Email - Excel Row ' . $row_count . ': ' . print_r($data, true));
                
                // Allow rows with fewer columns - they might just have empty trailing fields
                if (empty($data) || (count($data) == 1 && trim($data[0]) == '')) {
                    // Skip completely empty rows
                    error_log('Alumni Bulk Email - Skipping empty Excel row ' . $row_count);
                    continue;
                }
                
                // Store all rows regardless of email validation
                $valid_recipients++;
                $recipient = array();
                
                // Store all columns dynamically
                foreach ($cleaned_headers as $index => $column_name) {
                    $value = isset($data[$index]) ? trim((string)$data[$index]) : '';
                    $recipient[$column_name] = $value;
                }
                
                // Add tags column (initially empty for all recipients)
                $recipient['tags'] = '';
                
                // Generate standard fields for backward compatibility (only if we can extract them)
                $recipient['name'] = $this->extract_name_field($recipient);
                $recipient['first_name'] = $this->extract_first_name($recipient);
                $recipient['last_name'] = $this->extract_last_name($recipient);
                
                $recipients[] = $recipient;
            }
            
            error_log('Alumni Bulk Email - Excel parsing complete. Total rows: ' . $row_count . ', Valid recipients: ' . $valid_recipients);
            
        } catch (Exception $e) {
            error_log('Alumni Bulk Email - Excel parsing error: ' . $e->getMessage());
            throw $e;
        }
        
        return $recipients;
    }
    
    /**
     * Generate CSV content for export
     */
    public function generate_csv_content($recipients) {
        if (empty($recipients)) {
            return '';
        }
        
        // Get all column headers from the first recipient
        $headers = array_keys($recipients[0]);
        
        $output = fopen('php://temp', 'w');
        
        // Write headers
        fputcsv($output, $headers, ",", '"', "\\");
        
        // Write data rows
        foreach ($recipients as $recipient) {
            $row = array();
            foreach ($headers as $header) {
                $row[] = isset($recipient[$header]) ? $recipient[$header] : '';
            }
            fputcsv($output, $row, ",", '"', "\\");
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    /**
     * Generate Excel content for export
     */
    public function generate_excel_content($recipients) {
        if (empty($recipients)) {
            return '';
        }
        
        try {
            if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                throw new Exception('PHPSpreadsheet library not available');
            }
            
            // Create new spreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get all column headers from the first recipient
            $headers = array_keys($recipients[0]);
            
            // Write headers
            $col = 1;
            foreach ($headers as $header) {
                $worksheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }
            
            // Style the header row
            $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
            $worksheet->getStyle($headerRange)->getFont()->setBold(true);
            $worksheet->getStyle($headerRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            
            // Write data rows
            $row = 2;
            foreach ($recipients as $recipient) {
                $col = 1;
                foreach ($headers as $header) {
                    $value = isset($recipient[$header]) ? $recipient[$header] : '';
                    $worksheet->setCellValueByColumnAndRow($col, $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto-size columns
            foreach (range(1, count($headers)) as $columnID) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnID);
                $worksheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }
            
            // Create writer and generate content
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            // Capture output
            ob_start();
            $writer->save('php://output');
            $excel_content = ob_get_contents();
            ob_end_clean();
            
            return $excel_content;
            
        } catch (Exception $e) {
            throw new Exception('Excel generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Extract name field from recipient data
     */
    private function extract_name_field($recipient) {
        // Try common name column variations
        $name_columns = ['name', 'full_name', 'fullname', 'Name', 'Full Name', 'Full_Name'];
        
        foreach ($name_columns as $col) {
            if (isset($recipient[$col]) && !empty(trim($recipient[$col]))) {
                return trim($recipient[$col]);
            }
        }
        
        // If no name column found, try to construct from first/last name
        $first_name = $this->extract_first_name($recipient);
        $last_name = $this->extract_last_name($recipient);
        
        if ($first_name || $last_name) {
            return trim($first_name . ' ' . $last_name);
        }
        
        return '';
    }
    
    /**
     * Extract first name from recipient data
     */
    private function extract_first_name($recipient) {
        $first_name_columns = ['first_name', 'firstname', 'fname', 'First Name', 'First_Name', 'given_name'];
        
        foreach ($first_name_columns as $col) {
            if (isset($recipient[$col]) && !empty(trim($recipient[$col]))) {
                return trim($recipient[$col]);
            }
        }
        
        return '';
    }
    
    /**
     * Extract last name from recipient data
     */
    private function extract_last_name($recipient) {
        $last_name_columns = ['last_name', 'lastname', 'lname', 'Last Name', 'Last_Name', 'surname', 'family_name'];
        
        foreach ($last_name_columns as $col) {
            if (isset($recipient[$col]) && !empty(trim($recipient[$col]))) {
                return trim($recipient[$col]);
            }
        }
        
        return '';
    }
    
    /**
     * Extract email from recipient data
     */
    public function extract_email_from_recipient($recipient) {
        $email_columns = ['email', 'email_address', 'Email', 'Email Address', 'e-mail', 'mail'];
        
        foreach ($email_columns as $col) {
            if (isset($recipient[$col]) && !empty(trim($recipient[$col]))) {
                $email = trim($recipient[$col]);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Validate file upload
     */
    public function validate_file_upload($file) {
        // Check file was uploaded
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception('No file uploaded');
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['csv', 'xlsx', 'xls'];
        
        if (!in_array($extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Please upload a CSV or Excel file.');
        }
        
        // Check file size (limit to 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File too large. Maximum size is 10MB.');
        }
        
        return true;
    }
}