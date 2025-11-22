<?php

class Document_Parser {

    /**
     * Parse Word document and extract text with formatting
     */
    public static function parse_document($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if ($file_ext === 'docx') {
            // Use simple basic parser for consistent results
            return self::parse_docx($file_path);
        } elseif ($file_ext === 'doc') {
            return self::parse_doc($file_path);
        }

        return false;
    }

    /**
     * Parse DOCX (Office Open XML) file
     */
    private static function parse_docx($file_path) {
        try {
            // DOCX is a ZIP file, extract it
            $zip = new ZipArchive();
            $zip_result = $zip->open($file_path);

            if ($zip_result !== true) {
                return false;
            }

            $content = '';
            $pages = [];
            $current_page = 1;
            $current_content = '';

            // Read main document
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                $zip->close();
                return false;
            }

            // Load XML and extract text (suppress warnings)
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $load_result = $dom->loadXML($xml);
            libxml_clear_errors();

            if (!$load_result) {
                $zip->close();
                return false;
            }

            // Get all paragraphs
            $paragraphs = $dom->getElementsByTagName('p');
            $para_count = $paragraphs->length;

            if ($para_count === 0) {
                $zip->close();
                // Try alternative extraction method
                return self::parse_docx_alternative($zip, $dom);
            }

            foreach ($paragraphs as $para) {
                $para_text = self::extract_paragraph_text($para);

                // Check for explicit page break BEFORE adding content
                $has_break = self::has_page_break($para);

                // Add all content (even whitespace), we'll trim later
                $current_content .= $para_text . "\n";

                // Simple page break detection (every ~5000 chars or explicit break)
                if (strlen($current_content) > 5000 || $has_break) {
                    $trimmed_content = trim($current_content);

                    // Always create page if it has any content, or if we hit the size limit
                    if (!empty($trimmed_content) || strlen($current_content) > 5000) {
                        $title = self::extract_title($trimmed_content);
                        $pages[] = [
                            'page_number' => $current_page,
                            'content' => !empty($trimmed_content) ? $trimmed_content : '<p>(Empty page)</p>',
                            'title' => $title,
                        ];
                        $current_page++;
                        $current_content = '';
                    }
                }
            }

            // Add remaining content
            if (!empty(trim($current_content))) {
                $title = self::extract_title($current_content);
                $pages[] = [
                    'page_number' => $current_page,
                    'content' => trim($current_content),
                    'title' => $title,
                ];
            }

            $zip->close();

            return [
                'success' => true,
                'total_pages' => count($pages),
                'pages' => $pages,
            ];

        } catch (Exception $e) {
            return false;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Alternative parsing method for DOCX when standard method finds 0 paragraphs
     */
    private static function parse_docx_alternative($zip, $dom) {
        // Try to extract all text nodes
        $all_text = [];
        $text_elements = $dom->getElementsByTagName('t');

        foreach ($text_elements as $element) {
            $text = $element->nodeValue;
            if (!empty(trim($text))) {
                $all_text[] = $text;
            }
        }

        if (empty($all_text)) {
            $zip->close();
            return [
                'success' => true,
                'total_pages' => 0,
                'pages' => [],
            ];
        }

        // Combine all text
        $full_content = implode(' ', $all_text);

        // Split into pages
        $pages = self::split_content_into_pages($full_content);

        $zip->close();

        return [
            'success' => true,
            'total_pages' => count($pages),
            'pages' => $pages,
        ];
    }

    /**
     * Parse old DOC format (simplified)
     */
    private static function parse_doc($file_path) {
        // For .doc files, we can use a basic text extraction
        // or recommend users convert to .docx
        $content = self::extract_text_from_doc($file_path);

        if (!$content) {
            return false;
        }

        $pages = self::split_content_into_pages($content);

        return [
            'success' => true,
            'total_pages' => count($pages),
            'pages' => $pages,
        ];
    }

    /**
     * Extract text from DOC file (basic implementation)
     */
    private static function extract_text_from_doc($file_path) {
        try {
            $content = '';
            $f = fopen($file_path, 'rb');

            while (!feof($f)) {
                $line = fgets($f, 1024);
                if (stripos($line, 'Content-Type: text/plain') !== false) {
                    while (($line = fgets($f, 1024)) !== false) {
                        $content .= $line;
                    }
                    break;
                }
            }
            fclose($f);

            return $content;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Extract text content from a paragraph element
     */
    private static function extract_paragraph_text($para_element) {
        $text = '';
        $text_nodes = $para_element->getElementsByTagName('t');

        foreach ($text_nodes as $text_node) {
            $text .= $text_node->nodeValue;
        }

        return $text;
    }

    /**
     * Check if paragraph contains page break
     */
    private static function has_page_break($para_element) {
        $pPr = $para_element->getElementsByTagName('pPr')->item(0);
        if ($pPr) {
            $page_break = $pPr->getElementsByTagName('pageBreakBefore')->item(0);
            if ($page_break) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract title from first line of content
     */
    private static function extract_title($content) {
        $lines = explode("\n", $content);
        $first_line = trim($lines[0]);

        if (strlen($first_line) > 100) {
            return substr($first_line, 0, 100) . '...';
        }

        return $first_line ?: 'Untitled Section';
    }

    /**
     * Split content into pages
     */
    private static function split_content_into_pages($content, $chars_per_page = 5000) {
        $pages = [];
        $lines = explode("\n", $content);
        $current_page = 1;
        $current_content = '';

        foreach ($lines as $line) {
            $current_content .= $line . "\n";

            if (strlen($current_content) >= $chars_per_page) {
                $pages[] = [
                    'page_number' => $current_page,
                    'content' => trim($current_content),
                    'title' => self::extract_title($current_content),
                ];
                $current_page++;
                $current_content = '';
            }
        }

        if (!empty(trim($current_content))) {
            $pages[] = [
                'page_number' => $current_page,
                'content' => trim($current_content),
                'title' => self::extract_title($current_content),
            ];
        }

        return $pages;
    }
}
