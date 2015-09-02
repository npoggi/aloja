<?php

namespace alojaweb\Controller;

use alojaweb\inc\HighCharts;
use alojaweb\inc\Utils;
use alojaweb\inc\DBUtils;
use alojaweb\inc\MLUtils;

class MLCrossvarController extends AbstractController
{
	public function mlcrossvarAction()
	{
		$jsonData = array();
		$message = $instance = '';
		$must_wait = 'NO';
		try
		{
			$db = $this->container->getDBUtils();
		    	
		    	$where_configs = '';

			$preset = null;
			if (count($_GET) <= 1
			|| (count($_GET) == 2 && array_key_exists('current_model',$_GET))
			|| (count($_GET) == 3 && array_key_exists('variable1',$_GET) && array_key_exists('variable2',$_GET))
			|| (count($_GET) == 4 && array_key_exists('current_model',$_GET) && array_key_exists('variable1',$_GET) && array_key_exists('variable2',$_GET)))
			{
				$preset = Utils::initDefaultPreset($db, 'mlcrossvar');
			}
		        $selPreset = (isset($_GET['presets'])) ? $_GET['presets'] : "none";

			$params = array();
			$param_names = array('benchs','nets','disks','mapss','iosfs','replications','iofilebufs','comps','blk_sizes','id_clusters','datanodess','bench_types','vm_sizes','vm_coress','vm_RAMs','types','hadoop_versions'); // Order is important



			foreach ($param_names as $p) {
					$params[$p] = Utils::read_params($p,$where_configs);
					sort($params[$p]);
			}



			$params_additional = array();
			$param_names_additional = array('datefrom','dateto','minexetime','maxexetime','valids','filters'); // Order is important


			foreach ($param_names_additional as $p) { $params_additional[$p] = Utils::read_params($p,$where_configs,FALSE); }

			$cross_var1 = (array_key_exists('variable1',$_GET))?$_GET['variable1']:'maps';
			$cross_var2 = (array_key_exists('variable2',$_GET))?$_GET['variable2']:'exe_time';

			$where_configs = str_replace("AND .","AND ",$where_configs);
			$where_configs = str_replace("id_cluster","e.id_cluster",$where_configs);
			$cross_var1 = str_replace("id_cluster","e.id_cluster",$cross_var1);
			$cross_var2 = str_replace("id_cluster","e.id_cluster",$cross_var2);

			// compose instance
			$instance = MLUtils::generateSimpleInstance($param_names, $params, true, $db);
			$model_info = MLUtils::generateModelInfo($param_names, $params, true, $db);
			$slice_info = MLUtils::generateDatasliceInfo($param_names_additional, $params_additional);

			$rows = null;
			if ($cross_var1 != 'pred_time' && $cross_var2 != 'pred_time')
			{
				// Get stuff from the DB
				$query="SELECT ".$cross_var1." as V1,".$cross_var2." as V2
					FROM aloja2.execs e LEFT JOIN aloja2.clusters c ON e.id_cluster = c.id_cluster LEFT JOIN aloja2.JOB_details j ON e.id_exec = j.id_exec
					WHERE hadoop_version IS NOT NULL".$where_configs."
					ORDER BY RAND() LIMIT 5000;"; // FIXME - CLUMPSY PATCH FOR BYPASS THE BUG FROM HIGHCHARTS... REMEMBER TO ERASE THIS LINE WHEN THE BUG IS SOLVED
			    	$rows = $db->get_rows ( $query );
				if (empty($rows)) throw new \Exception('No data matches with your critteria.');
			}
			else
			{
				$other_var = $cross_var1;
				if ($cross_var1 == 'pred_time') $other_var = $cross_var2;

				// Call to MLTemplates, to fetch/learn model
				$_GET['pass'] = 1;
				$mltc1 = new MLTemplatesController();
				$mltc1->container = $this->container;
				$ret_learn = $mltc1->mlpredictionAction();

				if ($ret_learn == 1)
				{
					$must_wait = "YES";
					$jsonData = '[]';
					$categories1 = $categories2 = "''";
				}
				else if ($ret_learn == -1)
				{
					$must_wait = "NO";
					$jsonData = '[]';
					$categories1 = $categories2 = "''";
					$message = "There are no prediction models trained for such parameters. Train at least one model in 'ML Prediction' section. [".$instance."]";
				}
				else
				{
					$other_var = $cross_var1;
					if ($cross_var1 == 'pred_time') $other_var = $cross_var2;

					if ($cross_var1 == 'pred_time') { $var1 = 'p.'.$cross_var1; $var2 = 's.'.$cross_var2; }
					else { $var1 = 's.'.$cross_var1; $var2 = 'p.'.$cross_var2; }

					// Get stuff from the DB
					$query="SELECT ".$var1." as V1, ".$var2." as V2
						FROM (	SELECT ".$other_var.", e.id_exec
							FROM aloja2.execs e LEFT JOIN aloja2.clusters c ON e.id_cluster = c.id_cluster LEFT JOIN aloja2.JOB_details j ON e.id_exec = j.id_exec
							WHERE hadoop_version IS NOT NULL".$where_configs."
						) AS s LEFT JOIN aloja_ml.predictions AS p ON s.id_exec = p.id_exec
						ORDER BY RAND() LIMIT 5000;"; // FIXME - CLUMPSY PATCH FOR BYPASS THE BUG FROM HIGHCHARTS... REMEMBER TO ERASE THIS LINE WHEN THE BUG IS SOLVED
			  	  	$rows = $db->get_rows ( $query );
					if (empty($rows)) throw new \Exception('No data matches with your critteria.');
				}
			}

			if ($must_wait == "NO")
			{
				$map_var1 = $map_var2 = array();
				$count_var1 = $count_var2 = 0;
				$categories1 = $categories2 = '';

				$var1_categorical = in_array($cross_var1, array("net","disk","bench","vm_OS","provider","vm_size","type","bench_type"));
				$var2_categorical = in_array($cross_var2, array("net","disk","bench","vm_OS","provider","vm_size","type","bench_type"));

				foreach ($rows as $row)
				{
					$entry = array();

					if ($var1_categorical)
					{
						if (!array_key_exists($row['V1'],$map_var1))
						{
							$map_var1[$row['V1']] = $count_var1++;
							$categories1 = $categories1.(($categories1!='')?",":"")."\"".$row['V1']."\"";
						}
						$entry['y'] = $map_var1[$row['V1']]*(rand(990,1010)/1000);
					}
					else $entry['y'] = (int)$row['V1']*(rand(990,1010)/1000);

					if ($var2_categorical)
					{
						if (!array_key_exists($row['V2'],$map_var2))
						{
							$map_var2[$row['V2']] = $count_var2++;
							$categories2 = $categories2.(($categories2!='')?",":"")."\"".$row['V2']."\"";
						}
						$entry['x'] = $map_var2[$row['V2']]*(rand(990,1010)/1000);
					}
					else $entry['x'] = (int)$row['V2']*(rand(990,1010)/1000);

					$entry['name'] = $row['V1']." - ".$row['V2'];
					$jsonData[] = $entry;
				}

				$jsonData = json_encode($jsonData);
				if ($categories1 != '') $categories1 = "[".$categories1."]"; else $categories1 = "''";
				if ($categories2 != '') $categories2 = "[".$categories2."]"; else $categories2 = "''";
			}
			$cross_var1 = str_replace("e.id_cluster","id_cluster",$cross_var1);
			$cross_var2 = str_replace("e.id_cluster","id_cluster",$cross_var2);
		}
		catch(\Exception $e)
		{
			$this->container->getTwig ()->addGlobal ( 'message', $e->getMessage () . "\n" );
			$jsonData = '[]';
			$cross_var1 = $cross_var2 = '';
			$categories1 = $categories2 = '';
			$must_wait = "NO";
		}
		$return_params = array(
			'selected' => 'mlcrossvar',
			'jsonData' => $jsonData,
			'variable1' => $cross_var1,
			'variable2' => $cross_var2,
			'categories1' => $categories1,
			'categories2' => $categories2,
			'message' => $message,
			'instance' => $instance,
			'model_info' => $model_info,
			'slice_info' => $slice_info,
			'must_wait' => $must_wait,
			'preset' => $preset,
			'selPreset' => $selPreset,
			'options' => Utils::getFilterOptions($db)
		);
		foreach ($param_names as $p) $return_params[$p] = $params[$p];
		foreach ($param_names_additional as $p) $return_params[$p] = $params_additional[$p];

		echo $this->container->getTwig()->render('mltemplate/mlcrossvar.html.twig', $return_params);
	}

	public function mlcrossvar3dAction()
	{
		$jsonData = array();
		$message = $instance = '';
		$maxx = $minx = $maxy = $miny = $maxz = $minz = 0;
		$must_wait = 'NO';
		try
		{
			$db = $this->container->getDBUtils();
		    	
		    	$where_configs = '';
		    	
		        $preset = null;
			if (count($_GET) <= 1
			|| (count($_GET) == 2 && array_key_exists('current_model',$_GET))
			|| (count($_GET) == 3 && array_key_exists('variable1',$_GET) && array_key_exists('variable2',$_GET))
			|| (count($_GET) == 4 && array_key_exists('current_model',$_GET) && array_key_exists('variable1',$_GET) && array_key_exists('variable2',$_GET)))		
			{
				$preset = Utils::initDefaultPreset($db, 'mlcrossvar3d');		
			}
		        $selPreset = (isset($_GET['presets'])) ? $_GET['presets'] : "none";

			$params = array();
			$param_names = array('benchs','nets','disks','mapss','iosfs','replications','iofilebufs','comps','blk_sizes','id_clusters','datanodess','bench_types','vm_sizes','vm_coress','vm_RAMs','types','hadoop_versions'); // Order is important
			foreach ($param_names as $p) { $params[$p] = Utils::read_params($p,$where_configs); sort($params[$p]); }

			$params_additional = array();
			$param_names_additional = array('datefrom','dateto','minexetime','maxexetime','valids','filters'); // Order is important
			foreach ($param_names_additional as $p) { $params_additional[$p] = Utils::read_params($p,$where_configs,FALSE); }

			$cross_var1 = (array_key_exists('variable1',$_GET))?$_GET['variable1']:'maps';
			$cross_var2 = (array_key_exists('variable2',$_GET))?$_GET['variable2']:'net';
			$cross_var3 = 'exe_time';

			$where_configs = str_replace("AND .","AND ",$where_configs);
			$where_configs = str_replace("id_cluster","e.id_cluster",$where_configs);
			$cross_var1 = str_replace("id_cluster","e.id_cluster",$cross_var1);
			$cross_var2 = str_replace("id_cluster","e.id_cluster",$cross_var2);

			// compose instance
			$instance = MLUtils::generateSimpleInstance($param_names, $params, true, $db);
			$model_info = MLUtils::generateModelInfo($param_names, $params, true, $db);
			$slice_info = MLUtils::generateDatasliceInfo($param_names_additional, $params_additional);

			$rows = null;
			if ($cross_var1 != 'pred_time' && $cross_var2 != 'pred_time')
			{		
				// Get stuff from the DB
				$query="SELECT ".$cross_var1." as V1,".$cross_var2." as V2,".$cross_var3." as V3
					FROM aloja2.execs e LEFT JOIN aloja2.clusters c ON e.id_cluster = c.id_cluster LEFT JOIN aloja2.JOB_details j ON e.id_exec = j.id_exec
					WHERE hadoop_version IS NOT NULL".$where_configs."
					ORDER BY RAND() LIMIT 5000;"; // FIXME - CLUMPSY PATCH FOR BYPASS THE BUG FROM HIGHCHARTS... REMEMBER TO ERASE THIS LINE WHEN THE BUG IS SOLVED
			    	$rows = $db->get_rows ( $query );
				if (empty($rows)) throw new \Exception('No data matches with your critteria.');
			}
			else
			{
				$other_var = $cross_var1;
				if ($cross_var1 == 'pred_time') $other_var = $cross_var2;

				// Call to MLTemplates, to fetch/learn model
				$_GET['pass'] = 1;
				$mltc1 = new MLTemplatesController();
				$mltc1->container = $this->container;
				$ret_learn = $mltc1->mlpredictionAction();

				if ($ret_learn == 1)
				{
					$must_wait = "YES";
					$jsonData = '[]';
					$categories1 = $categories2 = "''";
				}
				else if ($ret_data == -1)
				{
					$must_wait = "NO";
					$jsonData = '[]';
					$categories1 = $categories2 = "''";
					$message = "There are no prediction models trained for such parameters. Train at least one model in 'ML Prediction' section. [".$instance."]";
				}
				else
				{
					$other_var = $cross_var1;
					if ($cross_var1 == 'pred_time') $other_var = $cross_var2;
					$other_var = str_replace("id_cluster","e.id_cluster",$other_var);

					if ($cross_var1 == 'pred_time') { $var1 = 'p.'.$cross_var1; $var2 = 's.'.$cross_var2; }
					else { $var1 = 's.'.$cross_var1; $var2 = 'p.'.$cross_var2; }
					$var3 = 'e.'.$cross_var3;

					// Get stuff from the DB
					$query="SELECT ".$var1." as V1, ".$var2." as V2,".$cross_var3." as V3
						FROM (	SELECT ".$other_var.", e.id_exec
							FROM aloja2.execs e LEFT JOIN aloja2.clusters c ON e.id_cluster = c.id_cluster LEFT JOIN aloja2.JOB_details j ON e.id_exec = j.id_exec
							WHERE hadoop_version IS NOT NULL".$where_configs."
						) AS s LEFT JOIN aloja_ml.predictions AS p ON s.id_exec = p.id_exec
						ORDER BY RAND() LIMIT 5000;"; // FIXME - CLUMPSY PATCH FOR BYPASS THE BUG FROM HIGHCHARTS... REMEMBER TO ERASE THIS LINE WHEN THE BUG IS SOLVED
			  	  	$rows = $db->get_rows ( $query );
					if (empty($rows)) throw new \Exception('No data matches with your critteria.');
				}
			}

			if ($must_wait == "NO")
			{
				$map_var1 = $map_var2 = array();
				$count_var1 = $count_var2 = 0;
				$categories1 = $categories2 = '';

				$var1_categorical = in_array($cross_var1, array("net","disk","bench","vm_OS","provider","vm_size","type","bench_type"));
				$var2_categorical = in_array($cross_var2, array("net","disk","bench","vm_OS","provider","vm_size","type","bench_type"));

				foreach ($rows as $row)
				{
					$entry = array();

					if ($var1_categorical)
					{
						if (!array_key_exists($row['V1'],$map_var1))
						{
							$map_var1[$row['V1']] = $count_var1++;
							$categories1 = $categories1.(($categories1!='')?",":"")."\"".$row['V1']."\"";
						}
						$entry['y'] = $map_var1[$row['V1']]*(rand(990,1010)/1000);
					}
					else $entry['y'] = (int)$row['V1']*(rand(990,1010)/1000);
					if ($entry['y'] > $maxy) $maxy = $entry['y'];
					if ($entry['y'] < $miny) $miny = $entry['y'];

					if ($var2_categorical)
					{
						if (!array_key_exists($row['V2'],$map_var2))
						{
							$map_var2[$row['V2']] = $count_var2++;
							$categories2 = $categories2.(($categories2!='')?",":"")."\"".$row['V2']."\"";
						}
						$entry['x'] = $map_var2[$row['V2']]*(rand(990,1010)/1000);
					}
					else $entry['x'] = (int)$row['V2']*(rand(990,1010)/1000);
					if ($entry['x'] > $maxx) $maxx = $entry['x'];
					if ($entry['x'] < $minx) $minx = $entry['x'];

					$entry['z'] = max(100,(int)$row['V3']*(rand(990,1010)/1000));
					if ($entry['z'] > $maxz) $maxz = $entry['z'];
					if ($entry['z'] < $minz) $minz = $entry['z'];

					$entry['name'] = $row['V1']." - ".$row['V2']." - ".max(100,(int)$row['V3']);

					$jsonData[] = $entry;
				}

				$jsonData = json_encode($jsonData);
				if ($categories1 != '') $categories1 = "[".$categories1."]"; else $categories1 = "''";
				if ($categories2 != '') $categories2 = "[".$categories2."]"; else $categories2 = "''";
			}
			$cross_var1 = str_replace("e.id_cluster","id_cluster",$cross_var1);
			$cross_var2 = str_replace("e.id_cluster","id_cluster",$cross_var2);
		}
		catch(\Exception $e)
		{
			$this->container->getTwig ()->addGlobal ( 'message', $e->getMessage () . "\n" );
			$jsonData = '[]';
			$cross_var1 = $cross_var2 = '';
			$categories1 = $categories2 = '';
			$maxx = $minx = $maxy = $miny = $maxz = $minz = 0;
			$must_wait = "NO";
		}
		$return_params = array(
			'selected' => 'mlcrossvar3d',
			'jsonData' => $jsonData,
			'variable1' => $cross_var1,
			'variable2' => $cross_var2,
			'categories1' => $categories1,
			'categories2' => $categories2,
			'maxx' => $maxx, 'minx' => $minx,
			'maxy' => $maxy, 'miny' => $miny,
			'maxz' => $maxz, 'minz' => $minz,
			'message' => $message,
			'instance' => $instance,
			'model_info' => $model_info,
			'slice_info' => $slice_info,
			'must_wait' => $must_wait,
			'preset' => $preset,
			'selPreset' => $selPreset,
			'options' => Utils::getFilterOptions($db)
		);
		foreach ($param_names as $p) $return_params[$p] = $params[$p];
		foreach ($param_names_additional as $p) $return_params[$p] = $params_additional[$p];

		echo $this->container->getTwig()->render('mltemplate/mlcrossvar3d.html.twig', $return_params);	
	}

	public function mlcrossvar3dfaAction()
	{
		$jsonData = array();
		$message = $instance = $possible_models_id = '';
		$maxx = $minx = $maxy = $miny = $maxz = $minz = 0;
		$must_wait = 'NO';
		try
		{
			$dbml = new \PDO($this->container->get('config')['db_conn_chain'], $this->container->get('config')['mysql_user'], $this->container->get('config')['mysql_pwd']);
			$dbml->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$dbml->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

			$db = $this->container->getDBUtils();
		    	
		    	$where_configs = '';

		        $preset = null;
			if (count($_GET) <= 1
			|| (count($_GET) == 2 && array_key_exists('current_model',$_GET))
			|| (count($_GET) == 3 && array_key_exists('variable1',$_GET) && array_key_exists('variable2',$_GET))
			|| (count($_GET) == 4 && array_key_exists('current_model',$_GET) && array_key_exists('variable1',$_GET) && array_key_exists('variable2',$_GET)))
			{
				$preset = Utils::initDefaultPreset($db, 'mlcrossvar3dfa');
			}
		        $selPreset = (isset($_GET['presets'])) ? $_GET['presets'] : "none";

			$params = array();
			$param_names = array('benchs','nets','disks','mapss','iosfs','replications','iofilebufs','comps','blk_sizes','id_clusters','datanodess','bench_types','vm_sizes','vm_coress','vm_RAMs','types','hadoop_versions'); // Order is important
			foreach ($param_names as $p) { $params[$p] = Utils::read_params($p,$where_configs,FALSE); sort($params[$p]); }

			$params_additional = array();
			$param_names_additional = array('datefrom','dateto','minexetime','maxexetime','valids','filters'); // Order is important
			foreach ($param_names_additional as $p) { $params_additional[$p] = Utils::read_params($p,$where_configs,FALSE); }

			$cross_var1 = (array_key_exists('variable1',$_GET))?$_GET['variable1']:'maps';
			$cross_var2 = (array_key_exists('variable2',$_GET))?$_GET['variable2']:'net';

			$unseen = (array_key_exists('unseen',$_GET) && $_GET['unseen'] == 1);

			$where_configs = str_replace("AND .","AND ",$where_configs);
			$cross_var1 = str_replace("id_cluster","e.id_cluster",$cross_var1);
			$cross_var2 = str_replace("id_cluster","e.id_cluster",$cross_var2);

			// compose instance
			$instance = MLUtils::generateSimpleInstance($param_names, $params, $unseen, $db);
			$model_info = MLUtils::generateModelInfo($param_names, $params, $unseen, $db);
			$slice_info = MLUtils::generateDatasliceInfo($param_names_additional, $params_additional);

			// Model for filling
			MLUtils::findMatchingModels($model_info, $possible_models, $possible_models_id, $dbml);

			$current_model = "";
			if (array_key_exists('current_model',$_GET) && in_array($_GET['current_model'],$possible_models_id)) $current_model = $_GET['current_model'];

			// Call to MLFindAttributes, to fetch data
			$_GET['pass'] = 1;
			$_GET['unseen'] = $unseen;
			$mlfa1 = new MLFindAttributesController();
			$mlfa1->container = $this->container;
			$ret_data = $mlfa1->mlfindattributesAction();

			$rows = null;
			if ($ret_data == 1)
			{
				$must_wait = "YES";
				$jsonData = '[]';
				$categories1 = $categories2 = "''";
			}
			else if ($ret_data == -1)
			{
				$must_wait = "NO";
				$jsonData = '[]';
				$categories1 = $categories2 = "''";
				$message = "There are no prediction models trained for such parameters. Train at least one model in 'ML Prediction' section. [".$instance."]";
			}
			else
			{
				// Get stuff from the DB
				$query="SELECT ".$cross_var1." AS V1, ".$cross_var2." AS V2, AVG(p.pred_time) as V3, p.instance
					FROM aloja_ml.predictions as p
					WHERE p.id_learner ".(($current_model != '')?"='".$current_model."'":"IN (SELECT id_learner FROM aloja_ml.trees WHERE model='".$model_info."')").$where_configs."
					GROUP BY p.instance
					ORDER BY RAND() LIMIT 5000;"; // TODO FIXME - CLUMPSY PATCH FOR BYPASS THE BUG FROM HIGHCHARTS... REMEMBER TO ERASE THIS LINE WHEN THE BUG IS SOLVED
				$rows = $dbml->query($query);
				if (empty($rows)) throw new \Exception('No data matches with your critteria.');
			}

			if ($must_wait == "NO")
			{
				$map_var1 = $map_var2 = array();
				$count_var1 = $count_var2 = 0;
				$categories1 = $categories2 = '';

				$var1_categorical = in_array($cross_var1, array("net","disk","bench","vm_OS","provider","vm_size","type","bench_type"));
				$var2_categorical = in_array($cross_var2, array("net","disk","bench","vm_OS","provider","vm_size","type","bench_type"));

				foreach ($rows as $row)
				{
					$entry = array();

					if ($var1_categorical)
					{
						if (!array_key_exists($row['V1'],$map_var1))
						{
							$map_var1[$row['V1']] = $count_var1++;
							$categories1 = $categories1.(($categories1!='')?",":"")."\"".$row['V1']."\"";
						}
						$entry['y'] = $map_var1[$row['V1']]*(rand(990,1010)/1000);
					}
					else $entry['y'] = (int)$row['V1']*(rand(990,1010)/1000);
					if ($entry['y'] > $maxy) $maxy = $entry['y'];
					if ($entry['y'] < $miny) $miny = $entry['y'];

					if ($var2_categorical)
					{
						if (!array_key_exists($row['V2'],$map_var2))
						{
							$map_var2[$row['V2']] = $count_var2++;
							$categories2 = $categories2.(($categories2!='')?",":"")."\"".$row['V2']."\"";
						}
						$entry['x'] = $map_var2[$row['V2']]*(rand(990,1010)/1000);
					}
					else $entry['x'] = (int)$row['V2']*(rand(990,1010)/1000);
					if ($entry['x'] > $maxx) $maxx = $entry['x'];
					if ($entry['x'] < $minx) $minx = $entry['x'];

					$entry['z'] = (int)$row['V3']*(rand(990,1010)/1000);
					if ($entry['z'] > $maxz) $maxz = $entry['z'];
					if ($entry['z'] < $minz) $minz = $entry['z'];

					$entry['name'] = $row['instance']; //$row['V1']." - ".$row['V2']." - ".max(100,(int)$row['V3']);
					$jsonData[] = $entry;
				}

				$jsonData = json_encode($jsonData);
				if ($categories1 != '') $categories1 = "[".$categories1."]"; else $categories1 = "''";
				if ($categories2 != '') $categories2 = "[".$categories2."]"; else $categories2 = "''";
			}

			$dbml = null;
			$cross_var1 = str_replace("e.id_cluster","id_cluster",$cross_var1);
			$cross_var2 = str_replace("e.id_cluster","id_cluster",$cross_var2);
		}
		catch(\Exception $e)
		{
			$this->container->getTwig ()->addGlobal ( 'message', $e->getMessage () . "\n" );
			$jsonData = '[]';
			$cross_var1 = $cross_var2 = '';
			$categories1 = $categories2 = '';
			$maxx = $minx = $maxy = $miny = $maxz = $minz = 0;
			$must_wait = "NO";
			$dbml = null;
			$possible_models = $possible_models_id = array();
		}
		$return_params = array(
			'selected' => 'mlcrossvar3dfa',
			'jsonData' => $jsonData,
			'variable1' => $cross_var1,
			'variable2' => $cross_var2,
			'categories1' => $categories1,
			'categories2' => $categories2,
			'maxx' => $maxx, 'minx' => $minx,
			'maxy' => $maxy, 'miny' => $miny,
			'maxz' => $maxz, 'minz' => $minz,
			'message' => $message,
			'instance' => $instance,
			'model_info' => $model_info,
			'slice_info' => $slice_info,
			'current_model' => $current_model,
			'unseen' => $unseen,
			'models' => '<li>'.implode('</li><li>',$possible_models).'</li>',
			'models_id' => $possible_models_id,
			'must_wait' => $must_wait,
			'preset' => $preset,
			'selPreset' => $selPreset,
			'options' => Utils::getFilterOptions($db)
		);
		foreach ($param_names as $p) $return_params[$p] = $params[$p];
		foreach ($param_names_additional as $p) $return_params[$p] = $params_additional[$p];

		echo $this->container->getTwig()->render('mltemplate/mlcrossvar3dfa.html.twig', $return_params);
	}
}
