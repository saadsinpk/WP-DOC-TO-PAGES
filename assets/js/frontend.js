// SID Doc to Book Frontend JavaScript

(function($) {
    'use strict';

    // Store current search term for highlighting
    let currentSearchTerm = '';

    $(document).ready(function() {
        // Check if we need to highlight search term from URL
        const urlParams = new URLSearchParams(window.location.search);
        const highlightTerm = urlParams.get('sdtb_highlight');
        if (highlightTerm) {
            currentSearchTerm = decodeURIComponent(highlightTerm);
            highlightAndScrollToTerm(currentSearchTerm);
        }

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
        $('#sdtb-search').on('keyup', function(e) {
            clearTimeout(searchTimeout);
            const searchTerm = $(this).val().trim();

            // Press Enter to search current page first
            if (e.which === 13 && searchTerm.length >= 2) {
                // First try to find on current page
                if (highlightAndScrollToTerm(searchTerm)) {
                    currentSearchTerm = searchTerm;
                    return;
                }
            }

            if (searchTerm.length < 2) {
                $('#sdtb-search-results').empty();
                clearHighlights();
                return;
            }

            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, 300);
        });

        $('#sdtb-search-btn').on('click', function() {
            const searchTerm = $('#sdtb-search').val().trim();
            if (searchTerm.length >= 2) {
                currentSearchTerm = searchTerm;
                // First try to highlight on current page
                highlightAndScrollToTerm(searchTerm);
                // Also search across all pages
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
                        displaySearchResults(response.data, searchTerm);
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

        function displaySearchResults(results, searchTerm) {
            const resultsContainer = $('#sdtb-search-results');
            resultsContainer.empty();

            if (results.length === 0) {
                resultsContainer.html('<p>No results found</p>');
                return;
            }

            // Get current page number
            const currentPage = parseInt($('.sdtb-page-number-input').val()) || 1;

            results.forEach(function(result) {
                // Extract a snippet around the search term
                const snippet = getSnippet(result.page_content, searchTerm);
                const isCurrentPage = result.page_number === currentPage;

                const resultHtml = `
                    <div class="sdtb-search-result-item ${isCurrentPage ? 'sdtb-current-page-result' : ''}"
                         data-page="${result.page_number}"
                         data-search-term="${encodeURIComponent(searchTerm)}">
                        <div class="sdtb-result-page">
                            Page ${result.page_number}
                            ${isCurrentPage ? '<span class="sdtb-current-badge">(Current Page)</span>' : ''}
                        </div>
                        <div class="sdtb-result-title">${result.page_title || '(No title)'}</div>
                        <div class="sdtb-result-snippet">${snippet}</div>
                    </div>
                `;

                resultsContainer.append(resultHtml);
            });

            // Click on result to go to page and highlight
            $('.sdtb-search-result-item').on('click', function() {
                const pageNum = $(this).data('page');
                const term = decodeURIComponent($(this).data('search-term'));
                const baseUrl = $('.sdtb-page-number-input').data('base-url');
                const currentPage = parseInt($('.sdtb-page-number-input').val()) || 1;

                // If clicking on current page result, just highlight and scroll
                if (pageNum === currentPage) {
                    highlightAndScrollToTerm(term);
                    return;
                }

                // Navigate to the page with highlight parameter
                let targetUrl = baseUrl;
                if (pageNum === 1) {
                    targetUrl = baseUrl + '?sdtb_highlight=' + encodeURIComponent(term);
                } else {
                    targetUrl = baseUrl + '?sdtb_page=' + pageNum + '&sdtb_highlight=' + encodeURIComponent(term);
                }
                window.location.href = targetUrl;
            });
        }

        // Get a text snippet around the search term
        function getSnippet(content, searchTerm) {
            // Strip HTML tags for snippet
            const textContent = content.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

            // Find the search term (case-insensitive)
            const lowerContent = textContent.toLowerCase();
            const lowerTerm = searchTerm.toLowerCase();
            const index = lowerContent.indexOf(lowerTerm);

            if (index === -1) {
                // Return first 100 characters if term not found in plain text
                return textContent.substring(0, 100) + '...';
            }

            // Get surrounding context (50 chars before and after)
            const start = Math.max(0, index - 50);
            const end = Math.min(textContent.length, index + searchTerm.length + 50);

            let snippet = '';
            if (start > 0) snippet += '...';
            snippet += textContent.substring(start, end);
            if (end < textContent.length) snippet += '...';

            // Highlight the search term in snippet
            const regex = new RegExp('(' + escapeRegExp(searchTerm) + ')', 'gi');
            snippet = snippet.replace(regex, '<mark class="sdtb-highlight-snippet">$1</mark>');

            return snippet;
        }

        // Highlight search term in page content and scroll to first match
        function highlightAndScrollToTerm(searchTerm) {
            if (!searchTerm || searchTerm.length < 2) return false;

            // Clear previous highlights
            clearHighlights();

            const pageContent = $('.sdtb-page-content');
            if (pageContent.length === 0) return false;

            // Use TreeWalker to find text nodes
            const walker = document.createTreeWalker(
                pageContent[0],
                NodeFilter.SHOW_TEXT,
                null,
                false
            );

            const textNodes = [];
            let node;
            while (node = walker.nextNode()) {
                if (node.nodeValue.trim()) {
                    textNodes.push(node);
                }
            }

            let foundMatch = false;
            let firstHighlight = null;
            const regex = new RegExp('(' + escapeRegExp(searchTerm) + ')', 'gi');

            textNodes.forEach(function(textNode) {
                const text = textNode.nodeValue;
                if (regex.test(text)) {
                    // Reset regex lastIndex
                    regex.lastIndex = 0;

                    // Create a wrapper span with highlighted content
                    const fragment = document.createDocumentFragment();
                    let lastIndex = 0;
                    let match;

                    while ((match = regex.exec(text)) !== null) {
                        // Add text before match
                        if (match.index > lastIndex) {
                            fragment.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
                        }

                        // Add highlighted match
                        const mark = document.createElement('mark');
                        mark.className = 'sdtb-search-highlight';
                        mark.textContent = match[1];
                        fragment.appendChild(mark);

                        if (!firstHighlight) {
                            firstHighlight = mark;
                        }

                        lastIndex = regex.lastIndex;
                        foundMatch = true;
                    }

                    // Add remaining text
                    if (lastIndex < text.length) {
                        fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
                    }

                    // Replace the text node with the fragment
                    textNode.parentNode.replaceChild(fragment, textNode);
                }
            });

            // Scroll to first highlight
            if (firstHighlight) {
                setTimeout(function() {
                    firstHighlight.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    // Add a pulse animation to draw attention
                    $(firstHighlight).addClass('sdtb-highlight-pulse');
                    setTimeout(function() {
                        $('.sdtb-search-highlight').removeClass('sdtb-highlight-pulse');
                    }, 2000);
                }, 100);
            }

            return foundMatch;
        }

        // Clear all search highlights
        function clearHighlights() {
            $('.sdtb-page-content .sdtb-search-highlight').each(function() {
                const text = $(this).text();
                $(this).replaceWith(document.createTextNode(text));
            });
            // Normalize text nodes
            $('.sdtb-page-content')[0]?.normalize();
        }

        // Escape special regex characters
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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

        // Clear highlights when search input is cleared
        $('#sdtb-search').on('input', function() {
            if ($(this).val().trim() === '') {
                clearHighlights();
                $('#sdtb-search-results').empty();
            }
        });
    });

})(jQuery);
