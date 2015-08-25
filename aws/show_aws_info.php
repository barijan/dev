<?php
/**
 * 各種情報取得(backlog向け)
 *
 * PHP versions 5
 * @package		develop
 * @author		Yoshikazu Suzuki <suzuki@bellact.jp>
 * @copyright	2015 bellact
 * 
 * ※要事前準備 ： aws configure
*/
//error_reporting(0);
date_default_timezone_set('Asia/Tokyo');

require_once('vendor/autoload.php');
use Aws\Ec2\Ec2Client;
use Aws\Rds\RdsClient;

$now=Date('Y-m-d H:i:s');

//EC2情報取得
$ec2= new Ec2Client([
		//'region'  => 'ap-southeast-1',
		'region'  => 'ap-northeast-1',
		'version' => '2015-04-15'
]);
$instances = $ec2->describeInstances();
$columns=sprintf(
				 "InstanceId,"						//[0]
				."ImageId,"							//[1]
				."State.Name,"						//[2]
				."PublicDnsName,"					//[3]
				."KeyName,"							//[4]
				."InstanceType,"					//[5]
				."LaunchTime,"						//[6]
				."Placement.AvailabilityZone,"		//[7]
				."SubnetId,"						//[8]
				."VpcId,"							//[9]
				."PrivateIpAddress,"				//[10]
				."PublicIpAddress,"					//[11]
				."SecurityGroups[].GroupId"			//[12]
				);
$ec2_infos = $instances->search('Reservations[].Instances[].['.$columns.']');
$ec2_outlist=array();
$index_list=array('instance_id','image_id','dns_name','kname','instance_type','launch_dtime','region','subnet_id','vpc_id','local_ip','public_ip','security_group','status');

$ec2_outlist[]='| '.implode(' | ',$index_list).' |';
foreach($ec2_infos as $info){
	$launch_dtime=$info[6]->__toString();
	$launch_dtime=substr($launch_dtime,0,19);
	$launch_dtime=str_replace('T',' ',$launch_dtime);
	
	$dt=new DateTime($launch_dtime,new DateTimeZone('UTC'));
	$launch_dtime=$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d H:i:s');
	$tmp=array(
				 'instance_id'=>$info[0]
				,'image_id'=>$info[1]
				,'dns_name'=>$info[3]
				,'kname'=>$info[4]
				,'instance_type'=>$info[5]
				,'launch_dtime'=>$launch_dtime
				,'region'=>$info[7]
				,'subnet_id'=>$info[8]
				,'vpc_id'=>$info[9]
				,'local_ip'=>$info[10]
				,'public_ip'=>$info[11]
				,'security_group'=>implode(',',$info[12])
				,'status'=>$info[2]
				);
	$ec2_outlist[]='| '.implode(' | ',$tmp).' |';
}
print "###EC2\n";
print implode("\n",$ec2_outlist)."\n\n";


//RDS情報取得
$rds= new RdsClient([
		//'region'  => 'ap-southeast-1',
		'region'  => 'ap-northeast-1',
		'version' => '2014-10-31'
]);

$dbinstances = $rds->describeDBInstances();
$columns=sprintf(
				 "DBInstanceIdentifier,"		//[0]
				."DBInstanceClass,"				//[1]
				."Engine,"						//[2]
				."DBInstanceStatus,"			//[3]
				."MasterUsername,"				//[4]
				."DBName,"						//[5]
				."Endpoint,"					//[6]
				."AllocatedStorage,"			//[7]
				."InstanceCreateTime,"			//[8]
				."DBSecurityGroups,"			//[9]
				."VpcSecurityGroups,"			//[10]
				."AvailabilityZone,"			//[11]
				."MultiAZ,"						//[12]
				."EngineVersion,"				//[13]
				."PubliclyAccessible,"			//[14]
				."StorageType"					//[15]
				);
$rds_infos = $dbinstances->search('DBInstances[].['.$columns.']');
$rds_outlist=array();
$index_list=array('instance_name','instance_type','db_engine','version','master_user','db_name','endpoint','port','strage','strage_type','create_dtime','security_group','region','multi_az','public','status');
$rds_outlist[]='| '.implode(' | ',$index_list).' |';

foreach($rds_infos as $info){
	$launch_dtime=$info[8]->__toString();
	$launch_dtime=substr($launch_dtime,0,19);
	$launch_dtime=str_replace('T',' ',$launch_dtime);
	
	$dt=new DateTime($launch_dtime,new DateTimeZone('UTC'));
	$launch_dtime=$dt->setTimeZone(new DateTimeZone('Asia/Tokyo'))->format('Y-m-d H:i:s');
	
	$security_group='';
	if($info[9]){
		$security_group=implode(',',$info[9]);
	}elseif(count($info[10])>0){
		$tmp_vpc=array();
		foreach($info[10] as $vpc_groups){
			if($vpc_groups['Status']=='active'){
				$tmp_vpc[]=$vpc_groups['VpcSecurityGroupId'];
			}
		}
		$security_group=implode(',',$tmp_vpc);
	}
	$multi_az="FALSE";
	if($info[12]==true){
		$multi_az="TRUE";
	}
	$public="FALSE";
	if($info[14]==true){
		$public="TRUE";
	}
	$tmp=array(
				 'instance_name'=>$info[0]
				,'instance_type'=>$info[1]
				,'db_engine'=>$info[2]
				,'version'=>$info[13]
				,'master_user'=>$info[4]
				,'db_name'=>$info[5]
				,'endpoint'=>$info[6]['Address']
				,'port'=>$info[6]['Port']
				,'strage'=>$info[7]
				,'strage_type'=>$info[15]
				,'create_dtime'=>$launch_dtime
				,'security_group'=>$security_group
				,'region'=>$info[11]
				,'multi_az'=>$multi_az
				,'public'=>$public
				,'status'=>$info[3]
				);
	$rds_outlist[]='| '.implode(' | ',$tmp).' |';
}
print "###RDS\n";
print implode("\n",$rds_outlist)."\n\n";


//SG情報取得
$sg = $ec2->describeSecurityGroups();
$columns=sprintf(
				 "OwnerId,"						//[0]
				."GroupName,"					//[1]
				."GroupId,"						//[2]
				."Description,"					//[3]
				."IpPermissions,"				//[4]
				."IpPermissionsEgress,"			//[5]
				."VpcId,"						//[6]
				);
$sg_infos = $sg->search('SecurityGroups[]');
$index_list=array('group_id','group_name','vpc_id','description','protocol','port','target');
$inbound=array();
$inbound[]='| '.implode(' | ',$index_list).' |';
$outbound=$inbound;
foreach($sg_infos as $info){
	$base=array(
				 'GroupId'=>$info['GroupId']
				,'GroupName'=>$info['GroupName']
				,'VpcId'=>$info['VpcId']
				,'Description'=>$info['Description']
			);
	//inbound
	if(count($info['IpPermissions'])>0){
		foreach($info['IpPermissions'] as $rec){
			$tmp=$base;
			if($rec['IpProtocol']==-1){
				$tmp['IpProtocol']='all';
			}else{
				$tmp['IpProtocol']=$rec['IpProtocol'];
			}
			if(isset($rec['FromPort']) && $rec['FromPort']){
				$tmp['Port']=$rec['FromPort'];
				if($rec['FromPort']!=$rec['ToPort']){
					$tmp['Port'].='-'.$rec['ToPort'];
				}
			}else{
				$tmp['Port']='all';
			}
			if(count($rec['UserIdGroupPairs'])>0){
				foreach($rec['UserIdGroupPairs'] as $target_rec){
					$tmp['target']=$target_rec['GroupId'];
					$inbound[]='| '.implode(' | ',$tmp).' |';
				}
			}else{
				foreach($rec['IpRanges'] as $target_rec){
					$tmp['target']=$target_rec['CidrIp'];
					$inbound[]='| '.implode(' | ',$tmp).' |';
				}
			}
		}
		
	}
	//outbound
	if(count($info['IpPermissionsEgress'])>0){
		foreach($info['IpPermissionsEgress'] as $rec){
			$tmp=$base;
			if($rec['IpProtocol']==-1){
				$tmp['IpProtocol']='all';
			}else{
				$tmp['IpProtocol']=$rec['IpProtocol'];
			}
			if(isset($rec['FromPort']) && $rec['FromPort']){
				$tmp['Port']=$rec['FromPort'];
				if($rec['FromPort']!=$rec['ToPort']){
					$tmp['Port'].='-'.$rec['ToPort'];
				}
			}else{
				$tmp['Port']='all';
			}
			if(count($rec['UserIdGroupPairs'])>0){
				foreach($rec['UserIdGroupPairs'] as $target_rec){
					$tmp['target']=$target_rec['GroupId'];
					$outbound[]='| '.implode(' | ',$tmp).' |';
				}
			}else{
				foreach($rec['IpRanges'] as $target_rec){
					$tmp['target']=$target_rec['CidrIp'];
					$outbound[]='| '.implode(' | ',$tmp).' |';
				}
			}
		}
	}
}

print "###Security Group(inbound)\n";
print implode("\n",$inbound)."\n\n";

print "###Security Group(outbound)\n";
print implode("\n",$outbound)."\n\n";




?>