<?php
session_start();
require('../auth/session_check.php');
require('../../../db.php');

function update_paths($dbh,$path,$new_path,$case_id)
{
	//Change paths of documents which reside in the recently changed folder
	$find_old_paths = $dbh->prepare('SELECT * FROM cm_documents WHERE folder = :old_path AND case_id = :case_id');

	$find_old_paths->bindParam(':old_path',$path);

	$find_old_paths->bindParam(':case_id',$case_id);

	$find_old_paths->execute();

	$paths = $find_old_paths->fetchAll(PDO::FETCH_ASSOC);

	foreach ($paths as $path_item) {

		$update_folders = $dbh->prepare("UPDATE cm_documents SET folder = '$new_path' WHERE id = '$path_item[id]'");

		$update_folders->execute();
	}

	//change the containing folder for every folder which resides in the recently changed folder

	$find_old_containers = $dbh->prepare("SELECT * FROM cm_documents WHERE containing_folder LIKE :container AND case_id = $case_id");

	$find_old_containers->bindParam(':container',$path);

	$find_old_containers->execute();

	$containers = $find_old_containers->fetchAll(PDO::FETCH_ASSOC);

	foreach ($containers as $container_item) {

		$pp = str_replace($path, '', $container_item['folder']);

		$new_subfolder_path = $new_path . $pp;

		$update_containers = $dbh->prepare("UPDATE cm_documents SET containing_folder = '$new_path', folder = '$new_subfolder_path' WHERE id = '$container_item[id]'");

		$update_containers->execute();
	}

	//Now, find all documents and subfolders that are further down the tree
	//1. Update the folder field
	$find_folder_fields = $dbh->prepare("SELECT * FROM cm_documents WHERE folder LIKE '$path%' AND case_id = '$case_id'");

	$find_folder_fields->execute();

	$folder_fields = $find_folder_fields->fetchAll(PDO::FETCH_ASSOC);

	foreach ($folder_fields as $folder_field) {
		//if the new path is not already in the folder field as a result of previous queries
		if (!stristr($folder_field['folder'],$new_path))
		{
			$descendants = str_replace($path, '', $folder_field['folder']);

			$new_folder_field = $new_path . $descendants;

			$update_folder_fields = $dbh->prepare("UPDATE cm_documents SET folder = '$new_folder_field' WHERE id = '$folder_field[id]'");

			$update_folder_fields->execute();
		}
	}

	//2.update the container field

	$find_container_fields = $dbh->prepare("SELECT * FROM cm_documents WHERE containing_folder LIKE '$path%' AND case_id = '$case_id'");

	$find_container_fields->execute();

	$container_fields = $find_container_fields->fetchAll(PDO::FETCH_ASSOC);

	foreach ($container_fields as $container_field) {

		//if the new path is not already in the container field as a result of previous queries
		if (!stristr($container_field['containing_folder'],$new_path))
		{
			$descendants = str_replace($path, '', $container_field['containing_folder']);

			$new_container_field = $new_path . $descendants;

			$update_container_fields = $dbh->prepare("UPDATE cm_documents SET containing_folder = '$new_container_field' WHERE id = '$container_field[id]'");

			$update_container_fields->execute();
		}
	}

}

function get_local_file_name($dbh,$id)
{
	$local_query = $dbh->prepare("SELECT id, local_file_name,extension FROM cm_documents WHERE id = '$id'");

	$local_query->execute();

	$data = $local_query->fetch();

	$name = $data['local_file_name'];

	$ext = $data['extension'];

	return array($name,$ext);
}

function strstr_after($haystack, $needle, $case_insensitive = false) {

    $strpos = ($case_insensitive) ? 'stripos' : 'strpos';

    $pos = $strpos($haystack, $needle);

    if (is_int($pos)) {
        return substr($haystack, $pos + strlen($needle));

    }

    // Most likely false or null
    return $pos;
}

//Create New Folder
$username = $_SESSION['login'];
$action = $_POST['action'];

if (isset($_POST['case_id'])) {
	$case_id = $_POST['case_id'];
}

if (isset($_POST['container'])){
	$container = $_POST['container'];}

if (isset($_POST['new_folder'])) {
	$new_folder = $_POST['new_folder'];
}

if (isset($_POST['new_name'])) {
	$new_name = $_POST['new_name'];
}

if (isset($_POST['item_id'])) {
	$item_id = $_POST['item_id'];
}

if (isset($_POST['doc_type'])) {
	$doc_type = $_POST['doc_type'];
}

if (isset($_POST['path'])) {
	$path = $_POST['path'];
}

if (isset($_POST['url'])) {
	$url = $_POST['url'];
}

if (isset($_POST['url_name'])) {
	$url_name = $_POST['url_name'];
}

if (isset($_POST['local_file_name'])) {
	$local_file_name = $_POST['local_file_name'];
}

if (isset($_POST['ccd_name'])) {
	$ccd_name = $_POST['ccd_name'];
}

if (isset($_POST['ccd_text'])) {
	$ccd_text = $_POST['ccd_text'];
}

if (isset($_POST['ccd_id'])) {
	$ccd_id = $_POST['ccd_id'];
}

if (isset($_POST['target_path'])) {
	$target_path = $_POST['target_path'];
}

if (isset($_POST['selection_path'])) {
	$selection_path = $_POST['selection_path'];
}

if ($action == 'newfolder')
{

	$new_folder_query = $dbh->prepare("INSERT INTO cm_documents (`id`, `name`, `local_file_name`, `folder`, `containing_folder`, `username`, `case_id`, `date_modified`) VALUES (NULL, '', '', :new_folder, :container, '$username', :case_id, CURRENT_TIMESTAMP);");

	$new_folder_query->bindParam(':container',$container);

	$new_folder_query->bindParam(':new_folder',$new_folder);

	$new_folder_query->bindParam(':case_id',$case_id);

	$new_folder_query->execute();

	$error = $dbh->errorInfo();

}

if ($action == 'rename')
{
	if ($doc_type === 'folder')
		{
			$sql = "UPDATE cm_documents SET folder = :new_path WHERE id = :item_id";

			if (strripos($path,'/'))  //path includes subdirectories
			{
				$last_slash = strripos($path,'/');
				$d = substr($path, 0,$last_slash);
				$new_path = $d . "/"  . $new_name;
			}
			else
			{
				$new_path = $new_name;  //path is in the root
			}

			$rename_query = $dbh->prepare($sql);

			$rename_query->bindParam(':item_id',$item_id);

			$rename_query->bindParam(':new_path',$new_path);

			//TODO must change the paths of every file and folder contained in the changed folder

		}
		else
		{
			$sql = "UPDATE cm_documents SET name = :new_name WHERE id = :item_id";

			$rename_query = $dbh->prepare($sql);

			$rename_query->bindParam(':new_name',$new_name);

			$rename_query->bindParam(':item_id',$item_id);

		}



	$rename_query->execute();

	$error = $rename_query->errorInfo();

	if ($doc_type === 'folder'  and !$error[1])
	{
		update_paths($dbh,$path,$new_path,$case_id);//$path is the old path, $new_path is the new path
	}

}

if ($action == 'delete')
{
	if ($doc_type === 'folder')
	{
		$delete_query = $dbh->prepare("DELETE from cm_documents WHERE folder LIKE :path_mask AND case_id = :case_id");

		$path_mask = $path . '%';

		$delete_query->bindParam(':path_mask',$path_mask);

		$delete_query->bindParam(':case_id',$case_id);

		$delete_query->execute();

		$error = $delete_query->errorInfo();
	}

	else

	{
		$file_name = get_local_file_name($dbh,$item_id);

		$delete_query = $dbh->prepare("DELETE from cm_documents WHERE id = :id");

		$delete_query->bindParam(':id',$item_id);

		$delete_query->execute();

		$error = $delete_query->errorInfo();

		if (!$error[1] and $file_name[1] != 'ccd' AND $file_name[1] != 'url')
			{unlink(CC_DOC_PATH . '/' . $file_name[0]);}

	}
}


if ($action == 'add_url')
{
	$add_url_query = $dbh->prepare("INSERT INTO cm_documents (id, name, local_file_name, extension, folder, containing_folder, username, case_id, date_modified) VALUES (NULL, :url_name, :url, 'url', :folder, '', :user, :case_id, CURRENT_TIMESTAMP);");

	$data = array('url_name' => $url_name, 'url' => $url, 'folder' => $path, 'user' => $username, 'case_id' =>$case_id);

	$add_url_query->execute($data);

	$error = $add_url_query->errorInfo();

}

if ($action == 'new_ccd')
{
	$new_ccd_query = $dbh->prepare("INSERT INTO cm_documents (id, name, local_file_name, extension, folder, containing_folder, text, write_permission, username, case_id, date_modified) VALUES (NULL, :ccd_name, :local_file_name, 'ccd', :folder, '','', :allowed_editors , :user, :case_id, CURRENT_TIMESTAMP);");

	$allowed_editors = serialize(array($username));

	$data = array('ccd_name' => $ccd_name, 'local_file_name' => $local_file_name, 'folder' => $path, 'user' => $username, 'case_id' =>$case_id,'allowed_editors'=>$allowed_editors);

	$new_ccd_query->execute($data);

	$error = $new_ccd_query->errorInfo();

}

if ($action == 'update_ccd')
{
	$update_ccd_query = $dbh->prepare("UPDATE cm_documents SET name = :name, local_file_name = :ccd_local_name, text = :ccd_text WHERE id = :doc_id");

	$ccd_local_name = $ccd_id . ".ccd";

	$data = array('name' => $ccd_name, 'ccd_local_name' => $ccd_local_name, 'doc_id' => $ccd_id, 'ccd_text' => $ccd_text);

	$update_ccd_query->execute($data);

	$error = $update_ccd_query->errorInfo();

}

if ($action == 'open')
{
	if ($doc_type === 'folder')
	{

	}

	else

	{
		$open_query = $dbh->prepare("SELECT * FROM cm_documents WHERE id = :item_id");

		$open_query->bindParam(':item_id',$item_id);

		$open_query->execute();

		$doc_properties = $open_query->fetch();

		$error = $open_query->errorInfo();

		switch ($doc_properties['extension']) {
			case 'url':
				$target_url = $doc_properties['local_file_name'];
				break;

			case 'ccd':
				$ccd_id = $doc_properties['id'];
				$ccd_title = $doc_properties['name'];
				$ccd_content = $doc_properties['text'];
				$allowed_editors = unserialize($doc_properties['write_permission']);
				if (in_array($username, $allowed_editors))
				{$ccd_permissions = 'yes';}
				else
					{$ccd_permissions = 'no';}
				break;

			default:
				$finfo = finfo_open(FILEINFO_MIME);
				$file = CC_DOC_PATH . "/" . $doc_properties['local_file_name'];
				header('Content-Description: File Transfer');
				//header("Content-type: application/force-download");
				header("Content-type:" . finfo_file($finfo, $file));
				//header('Content-Type: application/octet-stream');
				header('Content-disposition: attachment; filename="'. $doc_properties['name'] .'"');
				header('Content-Transfer-Encoding:  binary');
				header("Content-Length: ". filesize($file));
				header('Expires: 0');
    			header('Cache-Control: no-store, no-cache, must-revalidate');
				header('Pragma: no-cache');
				readfile(CC_DOC_PATH . "/" . $doc_properties['local_file_name']);
				exit;
				break;
		}
	}

}

if ($action == 'cut')
{
	if ($doc_type == 'folder') {

		//change the path of the selected folder

		if (stristr($selection_path, '/'))

			{$folder_name = substr(strrchr($selection_path,'/'),1);}

		else

			{$folder_name = $selection_path;}

		$new_selection_path = $target_path . "/" . $folder_name;

		$cut_query = $dbh->prepare("UPDATE cm_documents SET folder = :new_selection_path, containing_folder = :target_path WHERE id = :item_id");

		$data = array('target_path' => $target_path,'new_selection_path' => $new_selection_path,'item_id' => $item_id);

		$cut_query->execute($data);

		//change the path of any subfolders and documents in the selected folder

		$update_paths = $dbh->prepare("SELECT * FROM cm_documents WHERE folder LIKE :old_path AND case_id = :case_id");

			$old_path = $selection_path . "%";

			$data = array('old_path' => $old_path,'case_id' => $case_id);

			$update_paths->execute($data);

			$paths = $update_paths->fetchAll(PDO::FETCH_ASSOC);

			foreach ($paths as $path) {

				//$new_path = str_replace($selection_path, $target_path, $path['folder']);
				//$selection_path = "Target/two/twosub";
				//$target_path = "Target/One";
				// if (target path is in selection_path) // we are moving up the tree

				// 	{}

				// 	else

				// 	{add selection path to target path}	//we are moving down the tree
											// Target/One/two
				$subfolder_path_part = strstr_after($path['folder'], $selection_path);

				$new_subfolder_path = $new_selection_path . $subfolder_path_part;

				//this for folders only
				if ($path['name'] === '')
				{
					$pos = strrpos($new_subfolder_path, '/');

					$new_container = substr($new_subfolder_path, 0, $pos);

				}
				else
					{$new_container = '';}

				$update_items = $dbh->prepare("UPDATE cm_documents SET folder = :folder, containing_folder = :container WHERE id = $path[id]");

				$data = array('folder' => $new_subfolder_path, 'container' => $new_container);

				$update_items->execute($data);

			}


	} else {

		$cut_query = $dbh->prepare("UPDATE cm_documents SET folder = :target WHERE id = :item_id");

		$data = array('target' => $target_path,'item_id' => $item_id);

		$cut_query->execute($data);

	}


	$error = $cut_query->errorInfo();

}

//Handle mysql errors

	if($error[1])

		{
			$return = array('message'=>'Sorry,there was an error.','error'=>true);
			echo json_encode($return);
		}

		else
		{

			switch($_POST['action']){

			case "newfolder":
			$new_id = $dbh->lastInsertId();
			$return = array('message'=>'Folder Created','id'=>$new_id);
			echo json_encode($return);
			break;

			case "rename":
			if ($doc_type === 'folder')
			{$return = array('message'=>'Folder Renamed.','newPath'=>$new_path);}
			else
				{$return = array('message'=>'Item Renamed.');}
			echo json_encode($return);
			break;

			case "delete":
			if ($doc_type === 'folder')
			{$return = array('message'=>'Folder Deleted.');}
			else
				{$return =  array('message'=>'Item Deleted.','item_id'=>$item_id);}
			echo json_encode($return);
			break;

			case "add_url":
			$return = array('message'=>'Web address added.');
			echo json_encode($return);
			break;

			case "new_ccd":
			$new_id = $dbh->lastInsertId();
			$return = array('ccd_id'=>$new_id,'ccd_title'=>$ccd_name);
			echo json_encode($return);
			break;

			case "update_ccd":
			$return = array('message'=>'Changes saved','ccd_title'=>$ccd_name);
			echo json_encode($return);
			break;

			case "open":
			if (isset($target_url))
				{$return = array('target_url'=>$target_url);}
				else
				{$return = array('ccd_id'=>$ccd_id,'ccd_title'=>$ccd_title,'ccd_content'=>$ccd_content,'ccd_permissions'=>$ccd_permissions);}
			echo json_encode($return);
			break;

			case "cut":
			$return = array('message' => 'Item moved.');
			echo json_encode($return);
			break;

			}

		}