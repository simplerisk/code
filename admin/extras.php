<div class="row-fluid">
  <div class="span12">
    <div class="hero-unit">
      <h4>Custom Extras</h4>
      <p>It would be awesome if everything were free, right?  Hopefully the core SimpleRisk platform is able to serve all of your risk management needs.  But, if you find yourself still wanting more functionality, we&#39;ve developed a series of &quot;Extras&quot; that will do just that for just a few hundred bucks each for a perpetual license.
      </p>
      <table width="100%" class="table table-bordered table-condensed">
      <thead>
      <tr>
        <td width="155px"><b><u>Extra Name</u></b></td>
        <td><b><u>Description</u></b></td>
        <td width="60px"><b><u>Enabled</u></b></td>
      </tr>
      </thead>
      <tbody>
      <tr>
        <td width="155px"><b>Custom Authentication</b></td>
        <td>Currently provides support for Active Directory Authentication and Duo Security multi-factor authentication, but will have other custom authentication types in the future.</td>
        <td width="60px"><?php echo (custom_authentication_extra() ? 'Yes' : 'No'); ?></td>
      </tr>
      <tr>
        <td width="155px"><b>Team-Based Separation</b></td>
        <td>Restriction of risk viewing to team members the risk is categorized as.</td>
        <td width="60px"><?php echo (team_separation_extra() ? 'Yes' : 'No'); ?></td>
      </tr>
      <tr>
        <td width="155px"><b>Notifications</b></td>
        <td>Sends email notifications when risks are submitted, updated, mitigated, or reviewed and may be run on a schedule to notify users of risks in the Unreviewed or Past Due state.</td>
        <td width="60px"><?php echo (notification_extra() ? 'Yes' : 'No'); ?></td>
      </tr>
      <tr>
        <td width="155px"><b>Encrypted Database</b></td>
        <td>Encryption of sensitive text fields in the database.</td>
        <td width="60px"><?php echo (encryption_extra() ? 'Yes' : 'No'); ?></td>
      </tr>
      <tbody>
      </table>
      <p>If you are interested in adding these or other custom functionality to your SimpleRisk installation, please send an e-mail to <a href="mailto:extras@simplerisk.org?Subject=Interest%20in%20SimpleRisk%20Extras" target="_top">extras@simplerisk.org</a>.</p>
    </div>
  </div>
</div>
        