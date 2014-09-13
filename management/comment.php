          <div class="row-fluid">
            <div class="well">
              <?php view_top_table($id, $calculated_risk, $subject, $status, false); ?>
            </div>
          </div>
          <div class="row-fluid">
            <div class="well">
              <form name="add_comment" method="post" action="">
                <label><?php echo $lang['Comment']; ?></label>
                <textarea name="comment" cols="50" rows="3" id="comment"></textarea>
                <div class="form-actions">
                  <button type="submit" name="submit" class="btn btn-primary"><?php echo $lang['Submit']; ?></button>
                  <input class="btn" value="<?php echo $lang['Reset']; ?>" type="reset">
                </div>
              </form>
            </div>
          </div>
        