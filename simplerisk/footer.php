<?php if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; } ?>
                </div>
                <!-- End of content -->
                <!-- The app footer now lives pinned at the bottom of the left sidebar
                     (see sidebar.php / .sr-sidebar-footer) so it doesn't cost a row of
                     content height. -->
        	</div>
        	<!-- End of content-wrapper -->
		</div>
		<!-- End of scroll-content -->
  	</div>
  <!-- End Page wrapper  -->
</div>
<!-- End Wrapper -->
    
    <!-- ============================================================== -->

    
    
    
<?php
    // Alerts have to be at the end because this way it can display alerts that were generated during the rendering of the page
    setup_alert_requirements("..");
    get_alert();
?>
	<script>
    	$(function() {
    		// Fading out the preloader once everything is done rendering
    		$(".preloader").fadeOut();
        });
	</script>
  </body>
</html>