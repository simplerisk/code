          <div class="row-fluid">
            <div class="well">
              <?php view_top_table($id, $calculated_risk, $subject, $status, false); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <form name="close_risk" method="post" action="">
                <h4><?php echo $lang['CloseRisk']; ?></h4>
                <?php echo $lang['Reason']; ?>: <?php create_dropdown("close_reason"); ?><br />
                <label><?php echo $lang['CloseOutInformation']; ?></label>
                <textarea name="note" cols="50" rows="3" id="note"></textarea>
                <div class="form-actions">
                  <button type="submit" name="submit" class="btn btn-primary"><?php echo $lang['Submit']; ?></button>
                  <input class="btn" value="<?php echo $lang['Reset']; ?>" type="reset">
                </div>
              </form>
            </div>
          </div>
        