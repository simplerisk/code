<div class="row-fluid">
  <div class="span12">
    <div class="hero-unit">
      <form name="review_settings" method="post" action="">

<?php $review_levels = get_review_levels(); ?>

      <p><?php echo $lang['IWantToReviewHighRiskEvery']; ?> <input type="text" name="high" size="2" value="<?php echo $review_levels[0]['value']; ?>" /> <?php echo $lang['days']; ?>.</p>
      <p><?php echo $lang['IWantToReviewMediumRiskEvery']; ?> <input type="text" name="medium" size="2" value="<?php echo $review_levels[1]['value']; ?>" /> <?php echo $lang['days']; ?>.</p>
      <p><?php echo $lang['IWantToReviewLowRiskEvery']; ?> <input type="text" name="low" size="2" value="<?php echo $review_levels[2]['value']; ?>" /> <?php echo $lang['days']; ?>.</p>

      <input type="submit" value="<?php echo $lang['Update']; ?>" name="update_review_settings" />

      </form>
    </div>
  </div>
</div>
        
