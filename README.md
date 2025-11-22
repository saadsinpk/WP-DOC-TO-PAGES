# SID Doc to Book

A WordPress plugin to convert Word documents into interactive online books with full page display, text selection, and search functionality.

## Features

- **Upload Word Documents**: Support for both .docx and .doc formats
- **Automatic Content Extraction**: Parse and display book content across multiple pages
- **Text Selection & Copy**: Users can select and copy text from book pages
- **Search Functionality**: Full-text search across all book content
- **RTL Language Support**: Dedicated support for Urdu, Arabic, and mixed content
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile devices
- **Custom Post Type**: Books are stored as custom posts with easy management
- **Page Navigation**: Quick navigation between pages with go-to-page feature
- **Progress Bar**: Visual indicator of reading progress

## Installation

1. Download the plugin to `wp-content/plugins/sid_doc_to_book/`
2. Activate the plugin from WordPress Admin
3. The plugin will automatically create necessary database tables

## Usage

### Adding a Book

1. Go to **Books** in the WordPress admin menu
2. Click **Add New Book**
3. Fill in the book title and description
4. In the **Book Document** meta box:
   - Upload your Word document (.docx or .doc)
   - The content will be automatically parsed and split into pages
5. In the **Book Information** meta box:
   - Add the author name
   - Select the language (Urdu, Arabic, or Mixed)
   - Add a book thumbnail (featured image)
6. Click **Publish**

### Updating a Book

To update book content:
1. Edit the book post
2. Upload a new Word document (it will replace the old one)
3. Click **Reprocess Document** if needed
4. Update and save

### Displaying Books on Frontend

Books can be accessed at:
- Single book: `yoursite.com/book/book-slug/`
- All books archive: `yoursite.com/book/`

### Features for Readers

**Page Navigation:**
- Use Previous/Next buttons
- Enter page number and click Go
- View progress bar at the bottom

**Text Selection:**
- Select text directly on the page
- Click "Select All" to select entire page
- Click "Copy Selected" to copy to clipboard

**Search:**
- Use the search box at the top
- Search for any term in the entire book
- Click on results to jump to that page

## Database Schema

The plugin creates a custom table `wp_sdtb_book_pages`:
- `id`: Page ID
- `book_id`: Associated book post ID
- `page_number`: Page number
- `page_title`: Page title (extracted from content)
- `page_content`: Full page content
- `created_at`: Creation timestamp
- `updated_at`: Last update timestamp

## Supported File Formats

- **.docx** (Office Open XML) - Recommended
- **.doc** (Legacy Word format)

## Language Support

- **Urdu**: Full RTL support with proper text direction
- **Arabic**: Full RTL support with proper text direction
- **Mixed**: Urdu + Arabic content in same document

## Settings

Settings can be configured for each book:
- Author name
- Language selection
- Featured image (book thumbnail)
- Excerpt (shown on archive page)
- Full description

## Troubleshooting

### Pagination not working / Pages have gaps
- Use the **Page Repair Tool** in book edit page
- Scroll to "Page Repair Tool" meta box
- Click "🔧 Repair Page Numbers" button
- This renumbers all pages sequentially (1, 2, 3, ..., N)
- Takes less than 1 second

### Document not parsing correctly
- Ensure the file is a valid Word document
- Try converting the file to .docx format if using .doc
- Check server error logs for parsing errors

### Content not showing on frontend
- Verify the book is published
- Check that pages were created (view book edit page for page count)
- Ensure proper file permissions for uploads directory

### RTL text not displaying correctly
- Make sure language is set to Urdu/Arabic/Mixed
- Check that your theme supports RTL CSS

### Search not working
- Ensure pages are numbered sequentially (use Page Repair Tool if needed)
- Try searching for a common word from the document
- Check debug log for AJAX errors

## API Usage

### Get Book Information
```php
$book = Book_Manager::get_book($book_id);
```

### Get All Pages of a Book
```php
$pages = Book_Manager::get_book_pages($book_id, $page = 1, $per_page = 1);
```

### Get Single Page
```php
$page = Book_Manager::get_page($book_id, $page_number);
```

### Search in Book
```php
$results = Book_Manager::search_in_book($book_id, $search_term);
```

## Admin Customization

### Filter book content
```php
apply_filters('sdtb_page_content', $content, $page, $book_id);
```

### Modify page split logic
Edit the `split_content_into_pages()` method in `class-document-parser.php` to adjust how pages are divided.

## Performance Notes

- For books with 200+ pages, the plugin automatically creates optimized database indexes
- Search is optimized using SQL LIKE queries
- Page content is stored in LONGTEXT field to support large documents
- Consider using caching plugins for better performance on high-traffic sites

## Future Enhancements

- Bookmark/note-taking functionality
- PDF export
- Text highlighting
- Multiple language support
- Offline reading mode
- Social sharing

## License

GPL v2 or later

## Support

For issues and feature requests, please contact the plugin author.
