// SID Doc to Book Frontend JavaScript

(function($) {
    'use strict';

    $(document).ready(function() {
        // Select all text in page
        $('.sdtb-select-all').on('click', function() {
            const pageContent = $('.sdtb-page-content')[0];
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(pageContent);
            selection.removeAllRanges();
            selection.addRange(range);
        });

        // Copy selected text
        $('.sdtb-copy-selection').on('click', function() {
            const selection = window.getSelection();
            if (selection.toString()) {
                document.execCommand('copy');
                alert(sdtbData.copySuccess || 'Text copied to clipboard!');
            } else {
                alert(sdtbData.selectText || 'Please select text first');
            }
        });

        // Go to page button
        $('.sdtb-go-btn').on('click', function() {
            const pageNum = parseInt($('.sdtb-page-number-input').val());
            const maxPage = parseInt($('.sdtb-page-number-input').attr('max'));
            const baseUrl = $('.sdtb-page-number-input').data('base-url');

            if (pageNum < 1 || pageNum > maxPage) {
                alert('Invalid page number');
                return;
            }

            // Build proper URL with query parameter
            if (pageNum === 1) {
                window.location.href = baseUrl;
            } else {
                window.location.href = baseUrl + '?sdtb_page=' + pageNum;
            }
        });

        // Enter key on page input
        $('.sdtb-page-number-input').on('keypress', function(e) {
            if (e.which === 13) {
                $('.sdtb-go-btn').click();
            }
        });

        // Search functionality
        let searchTimeout;
        $('#sdtb-search').on('keyup', function() {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val().trim();

            if (searchTerm.length < 2) {
                $('#sdtb-search-results').empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, 300);
        });

        $('#sdtb-search-btn').on('click', function() {
            const searchTerm = $('#sdtb-search').val().trim();
            if (searchTerm.length >= 2) {
                performSearch(searchTerm);
            }
        });

        function performSearch(searchTerm) {
            const bookId = getBookIdFromUrl();
            console.log('Searching in book ID:', bookId, 'for term:', searchTerm);

            $('#sdtb-search-results').html('<p>Searching...</p>');

            $.ajax({
                url: sdtbData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sdtb_search',
                    nonce: sdtbData.nonce,
                    book_id: bookId,
                    search_term: searchTerm
                },
                success: function(response) {
                    console.log('Search response:', response);
                    if (response.success) {
                        displaySearchResults(response.data);
                    } else {
                        $('#sdtb-search-results').html('<p style="color: red;">Error: ' + (response.data || 'Unknown error') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Search error:', error, xhr);
                    $('#sdtb-search-results').html('<p style="color: red;">Search failed. Check browser console.</p>');
                }
            });
        }

        function displaySearchResults(results) {
            const resultsContainer = $('#sdtb-search-results');
            resultsContainer.empty();

            if (results.length === 0) {
                resultsContainer.html('<p>No results found</p>');
                return;
            }

            results.forEach(function(result) {
                const resultHtml = `
                    <div class="sdtb-search-result-item" data-page="${result.page_number}">
                        <div class="sdtb-result-page">Page ${result.page_number}</div>
                        <div class="sdtb-result-title">${result.page_title}</div>
                    </div>
                `;

                resultsContainer.append(resultHtml);
            });

            // Click on result to go to page
            $('.sdtb-search-result-item').on('click', function() {
                const pageNum = $(this).data('page');
                const baseUrl = $('.sdtb-page-number-input').data('base-url');
                if (pageNum === 1) {
                    window.location.href = baseUrl;
                } else {
                    window.location.href = baseUrl + '?sdtb_page=' + pageNum;
                }
            });
        }

        function getBookIdFromUrl() {
            // Get book ID from data attribute in template
            const bookId = $('.sdtb-book-container').data('book-id');
            if (bookId) {
                return bookId;
            }
            // Fallback: extract from URL
            const pathArray = window.location.pathname.split('/');
            return pathArray[2];
        }
    });

})(jQuery);
