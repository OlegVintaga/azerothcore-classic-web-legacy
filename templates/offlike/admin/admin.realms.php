<br>
<?php builddiv_start(1, "Realms") ?>
<p style="font-size:0.8em;">Игровая таблица <b>realmlist</b> (auth-база AzerothCore) — только для чтения.<br>
Здесь настраиваются доступ сайта к базам world/characters и Remote Access каждого реальма.
Новый реальм добавляется в игровой <b>realmlist</b> (и в <b>website_realm_settings</b> — автоматически установщиком или этой страницей после появления реальма в auth).</p>
<table id="notification_body" class="forum_category" width="100%">
    <thead>
        <tr>
            <td width="12" align="center">#</td>
            <td>Name</td>
            <td width="80">Address</td>
            <td width="40" align="center">Port</td>
            <td align="center">Type</td>
            <td width="70">Timezone</td>
			<td align="center">RA address</td>
			<td align="center">RA Port</td>
			<td align="center">RA user</td>
			<td align="center">World DB</td>
			<td align="center">Chars DB</td>
        </tr>
    </thead>
<?php if(empty($_GET['action'])){ ?>
    <tbody>
<?php foreach($items as $item){ ?>
        <tr class="normal">
            <td align="center"><b><?php echo $item['id']; ?></b></td>
            <td class="n_title"><a href="index.php?n=admin&sub=realms&action=edit&id=<?php echo $item['id']; ?>" title="EDIT"><?php echo $item['name']; ?></a></td>
            <td><?php echo $item['address']; ?></td>
            <td align="center"><?php echo $item['port']; ?></td>
            <td align="center"><?php echo $realm_type_def[$item['icon']]; ?></td>
            <td><?php echo $realm_timezone_def[$item['timezone']]; ?></td>
			<td align="center"><?php echo $item['ra_address']; ?></td>
			<td align="center"><?php echo $item['ra_port']; ?></td>
			<td align="center"><?php echo $item['ra_user']; ?></td>
			<td align="center"><?php echo $item['dbname']; ?></td>
			<td align="center"><?php echo $item['chardbname']; ?></td>
        </tr>
<?php } ?>
    </tbody>
<?php }elseif($_GET['action']=='edit'){ ?>
    <tbody>
    <form action="index.php?n=admin&sub=realms&action=update&id=<?php echo $item['id']; ?>" method="post" onSubmit="return confirm('<?php echo $lang['sure_q'];?>');">
        <tr>
            <td align="center" colspan="11"><br />Realm ID: <b><?php echo $item['id']; ?></b> — <?php echo $item['name']; ?> (<?php echo $item['address']; ?>:<?php echo $item['port']; ?>) — игровые поля не редактируются</td>
		</tr>
		<tr>
			<td colspan="4">Remote Access Address: <input type="text" name="ra_address" value="<?php echo $item['ra_address']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">Remote Access Port: <input type="text" name="ra_port" value="<?php echo $item['ra_port']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">Remote Access Account Username: <input type="text" name="ra_user" value="<?php echo $item['ra_user']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">Remote Access Account Password: <input type="password" name="ra_pass" value="<?php echo $item['ra_pass']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4"><br />World DB Host: <input type="text" name="dbhost" value="<?php echo $item['dbhost']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">World DB Port: <input type="text" name="dbport" value="<?php echo $item['dbport']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">DB User: <input type="text" name="dbuser" value="<?php echo $item['dbuser']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">DB Password: <input type="password" name="dbpass" value="<?php echo $item['dbpass']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">World DB Name: <input type="text" name="dbname" value="<?php echo $item['dbname']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">Characters DB Name: <input type="text" name="chardbname" value="<?php echo $item['chardbname']; ?>" style="width:100%;"></td>
		</tr>
		<tr>
			<td colspan="4">Template: <input type="text" name="template" value="<?php echo $item['template']; ?>" style="width:100%;"></td>
		</tr>
		<tr><br /></tr>
        <tr>
            <td colspan="11" style="background:#a7a7a7;height:8px;border:2px solid #000;">
                <input type="submit" value="Update realm settings">
            </td>
        </tr>
    </form>
    </tbody>
<?php } ?>
</table>
<?php builddiv_end() ?>
