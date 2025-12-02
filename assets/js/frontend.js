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
            // Delay to ensure page is fully loaded
            setTimeout(function() {
                highlightAndScrollToTerm(currentSearchTerm);
            }, 300);
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

        // Copy selected text (using modern Clipboard API with fallback)
        $('.sdtb-copy-selection').on('click', function() {
            const selection = window.getSelection();
            if (selection.toString()) {
                copyToClipboard(selection.toString()).then(function() {
                    showNotification(sdtbData.copySuccess || 'Text copied to clipboard!', 'success');
                }).catch(function() {
                    showNotification('Failed to copy text', 'error');
                });
            } else {
                showNotification(sdtbData.selectText || 'Please select text first', 'warning');
            }
        });

        // Copy Link button
        $('.sdtb-share-copy').on('click', function() {
            const url = $(this).data('url');
            const btn = $(this);

            copyToClipboard(url).then(function() {
                btn.addClass('copied');
                showNotification('Link copied to clipboard!', 'success');
                setTimeout(function() {
                    btn.removeClass('copied');
                }, 2000);
            }).catch(function() {
                showNotification('Failed to copy link', 'error');
            });
        });

        // Print button
        $('.sdtb-print-btn').on('click', function() {
            window.print();
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
            if (e.which === 13 && searchTerm.length >= 1) {
                // First try to find on current page
                if (highlightAndScrollToTerm(searchTerm)) {
                    currentSearchTerm = searchTerm;
                    return;
                }
                // If not found on current page, search all pages
                performSearch(searchTerm);
                return;
            }

            if (searchTerm.length < 1) {
                $('#sdtb-search-results').empty();
                clearHighlights();
                return;
            }

            // Auto-search after typing (with delay)
            searchTimeout = setTimeout(function() {
                performSearch(searchTerm);
            }, 500);
        });

        $('#sdtb-search-btn').on('click', function() {
            const searchTerm = $('#sdtb-search').val().trim();
            if (searchTerm.length >= 1) {
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
            if (!searchTerm || searchTerm.length < 1) return false;

            // Clear previous highlights
            clearHighlights();

            const pageContent = $('.sdtb-page-content');
            if (pageContent.length === 0) return false;

            // Normalize the search term for better matching
            const normalizedSearchTerm = normalizeArabicText(searchTerm);

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
            let matchCount = 0;

            // Create regex that handles both the original and normalized text
            const regex = new RegExp('(' + escapeRegExp(searchTerm) + ')', 'gi');
            const normalizedRegex = new RegExp('(' + escapeRegExp(normalizedSearchTerm) + ')', 'gi');

            textNodes.forEach(function(textNode) {
                const text = textNode.nodeValue;
                const normalizedText = normalizeArabicText(text);

                // Try original search first, then normalized
                let matchRegex = regex;
                let testText = text;

                if (!regex.test(text)) {
                    regex.lastIndex = 0;
                    // Try normalized match
                    if (normalizedRegex.test(normalizedText)) {
                        normalizedRegex.lastIndex = 0;
                        matchRegex = normalizedRegex;
                        testText = normalizedText;
                    } else {
                        normalizedRegex.lastIndex = 0;
                        return; // No match in this node
                    }
                }
                matchRegex.lastIndex = 0;

                // Create a wrapper span with highlighted content
                const fragment = document.createDocumentFragment();
                let lastIndex = 0;
                let match;

                // Use original text for display, but normalized for matching
                while ((match = matchRegex.exec(testText)) !== null) {
                    // Add text before match
                    if (match.index > lastIndex) {
                        fragment.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
                    }

                    // Add highlighted match (use original text at match position)
                    const mark = document.createElement('mark');
                    mark.className = 'sdtb-search-highlight';
                    mark.setAttribute('data-match-index', matchCount);
                    mark.textContent = text.substring(match.index, match.index + match[1].length);
                    fragment.appendChild(mark);

                    if (!firstHighlight) {
                        firstHighlight = mark;
                    }

                    lastIndex = match.index + match[1].length;
                    matchCount++;
                    foundMatch = true;
                }

                // Add remaining text
                if (lastIndex < text.length) {
                    fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
                }

                // Replace the text node with the fragment
                if (foundMatch) {
                    textNode.parentNode.replaceChild(fragment, textNode);
                }
            });

            // Scroll to first highlight with better visibility
            if (firstHighlight) {
                setTimeout(function() {
                    // Get element position
                    const rect = firstHighlight.getBoundingClientRect();
                    const absoluteTop = window.pageYOffset + rect.top;

                    // Scroll to position with offset for better visibility
                    window.scrollTo({
                        top: absoluteTop - (window.innerHeight / 3),
                        behavior: 'smooth'
                    });

                    // Add a pulse animation to draw attention
                    $(firstHighlight).addClass('sdtb-highlight-pulse');

                    // Show match count notification
                    if (matchCount > 0) {
                        showNotification(matchCount + ' match' + (matchCount > 1 ? 'es' : '') + ' found', 'success');
                    }

                    setTimeout(function() {
                        $('.sdtb-search-highlight').removeClass('sdtb-highlight-pulse');
                    }, 3000);
                }, 150);
            }

            return foundMatch;
        }

        // Normalize Arabic/Urdu text for better search matching
        function normalizeArabicText(text) {
            if (!text) return '';
            return text
                // Remove diacritics (tashkeel)
                .replace(/[\u064B-\u065F\u0670]/g, '')
                // Normalize alef variations
                .replace(/[\u0622\u0623\u0625\u0627]/g, '\u0627')
                // Normalize heh/teh marbuta
                .replace(/\u0629/g, '\u0647')
                // Normalize yeh variations
                .replace(/[\u0649\u064A]/g, '\u064A')
                // Remove tatweel
                .replace(/\u0640/g, '')
                // Trim whitespace
                .trim();
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

        // Helper function: Copy to clipboard (modern API with fallback)
        function copyToClipboard(text) {
            return new Promise(function(resolve, reject) {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(resolve).catch(reject);
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        resolve();
                    } catch (err) {
                        reject(err);
                    }
                    document.body.removeChild(textArea);
                }
            });
        }

        // Helper function: Show notification toast
        function showNotification(message, type) {
            // Remove existing notifications
            $('.sdtb-notification').remove();

            const bgColors = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };

            const textColors = {
                success: '#fff',
                error: '#fff',
                warning: '#212529',
                info: '#fff'
            };

            const notification = $('<div class="sdtb-notification"></div>')
                .text(message)
                .css({
                    position: 'fixed',
                    bottom: '20px',
                    left: '50%',
                    transform: 'translateX(-50%)',
                    padding: '12px 24px',
                    background: bgColors[type] || bgColors.info,
                    color: textColors[type] || textColors.info,
                    borderRadius: '4px',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                    zIndex: 99999,
                    fontWeight: 'bold',
                    fontSize: '14px',
                    opacity: 0,
                    transition: 'opacity 0.3s ease'
                });

            $('body').append(notification);

            // Fade in
            setTimeout(function() {
                notification.css('opacity', 1);
            }, 10);

            // Fade out and remove
            setTimeout(function() {
                notification.css('opacity', 0);
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    });

})(jQuery);
