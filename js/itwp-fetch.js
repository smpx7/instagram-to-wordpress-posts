jQuery(document).ready(function($) {
    var $fetchButton = $('#itwp-fetch-btn');
    var $progressBar = $('#itwp-fetch-progress');

    if ($fetchButton.length && $progressBar.length) {
        $fetchButton.on('click', function(e) {
            e.preventDefault();

            $progressBar.show();
            $progressBar.val(0);

            $.ajax({
                url: itwp_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'itwp_manual_fetch',
                    security: itwp_ajax_obj.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var total = response.data.total;
                        var fetched = 0;

                        function fetchNextBatch() {
                            $.ajax({
                                url: itwp_ajax_obj.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'itwp_fetch_next_batch',
                                    security: itwp_ajax_obj.nonce
                                },
                                success: function(res) {
                                    if (res.success) {
                                        fetched += res.data.fetched;
                                        $progressBar.val((fetched / total) * 100);

                                        if (fetched < total) {
                                            fetchNextBatch();
                                        } else {
                                            alert('All Instagram posts have been fetched successfully!');
                                            $progressBar.hide();
                                            if (window.location.href.includes('post_type=instagram_post')) {
                                                location.reload(); // Reload the listing page to show the new posts
                                            }
                                        }
                                    } else {
                                        // Check if res.data exists and contains a message
                                        var errorMessage = res.data && res.data.message ? res.data.message : 'Error fetching Instagram posts.';
                                        alert(errorMessage);
                                        $progressBar.hide();
                                    }
                                },
                                error: function(xhr, status, error) {
                                    alert('AJAX Error: ' + error);
                                    $progressBar.hide();
                                }
                            });
                        }

                        fetchNextBatch();
                    } else {
                        // Check if response.data exists and contains a message
                        var errorMessage = response.data && response.data.message ? response.data.message : 'Error starting the fetch process.';
                        alert(errorMessage);
                        $progressBar.hide();
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX Error: ' + error);
                    $progressBar.hide();
                }
            });
        });
    }
});
