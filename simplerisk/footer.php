                </div>
                <!-- End of content -->
                <!-- footer -->
                <footer class="footer text-center">
                  Copyright 2025 SimpleRisk, Inc.  All rights reserved.
                  <a href="#"></a>
                </footer>
                <!-- End footer -->
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