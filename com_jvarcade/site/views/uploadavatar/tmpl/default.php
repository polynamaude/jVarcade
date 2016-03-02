<?php
/**
 * @package		jVArcade
 * @version		2.13
 * @date		2016-02-18
 * @copyright		Copyright (C) 2007 - 2014 jVitals Digital Technologies Inc. All rights reserved.
 * @license		http://www.gnu.org/copyleft/gpl.html GNU/GPLv3 or later
 * @link		http://jvitals.com
 */


defined('_JEXEC') or die;?>

<div id="j-sidebar-container" class="span2">
<?php echo jvaHelper::showProfileAvatar($this->user_id); ?>
</div>
<div id="j-main-container" class="span10">
<form enctype="multipart/form-data" action="index.php?option=com_jvarcade&task=uploadavatar.upload" method="post">
	<input class="input_box" name="avatar" type="file" size="35" />
	<button class="btn btn-primary" type="submit" >Upload Avatar</button>
	<input type="hidden" name="id" value="<?php echo $this->user_id; ?>" />
</form>
</div>
