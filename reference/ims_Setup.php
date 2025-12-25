<?php

namespace XenAddons\IMS;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\Widget;
use XF\Service\RebuildNestedSet;
use XenAddons\IMS\Install\Data\MySql;

use XenAddons\IMS\Repository\ItemField;
use XenAddons\IMS\Repository\ItemPrefix;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;
	
	// ################################ INSTALL STEPS ####################
	
	public function installStep1()
	{
		$sm = $this->schemaManager();
	
		foreach ($this->getTables() AS $tableName => $closure)
		{
			$sm->createTable($tableName, $closure);
		}
	}
	
	public function installStep2()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_ims_item_count', 'int')->setDefault(0);
			$table->addColumn('xa_ims_review_count', 'int')->setDefault(0);
			$table->addColumn('xa_ims_series_count', 'int')->setDefault(0);
			$table->addKey('xa_ims_item_count', 'ims_item_count');
			$table->addKey('xa_ims_review_count', 'ims_review_count');
			$table->addKey('xa_ims_series_count', 'ims_series_count');
		});
	}
	
	public function installStep3()
	{
		foreach ($this->getData() AS $dataSql)
		{
			$this->query($dataSql);
		}
	
		foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
		{
			$widgetFn($widgetKey);
		}
		
		$this->insertThreadType('ims_item', 'XenAddons\IMS:Item', 'XenAddons/IMS');
	}
	
	public function postInstall(array &$stateChanges)
	{
		if ($this->applyDefaultPermissions())
		{
			$this->app->jobManager()->enqueueUnique(
				'permissionRebuild',
				'XF:PermissionRebuild',
				[],
				false
			);
		}
	
		/** @var \XF\Service\RebuildNestedSet $service */
		$service = \XF::service('XF:RebuildNestedSet', 'XenAddons\IMS:Category', [
			'parentField' => 'parent_category_id'
		]);
		$service->rebuildNestedSetInfo();
	
		\XF::repository('XenAddons\IMS:ItemPrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\IMS:ItemField')->rebuildFieldCache();
		\XF::repository('XenAddons\IMS:ReviewField')->rebuildFieldCache();
		\XF::repository('XenAddons\IMS:UpdateField')->rebuildFieldCache();
	}
	
	
	// ################################ UPGRADE STEPS ####################

	
	// ################################ UPGRADE TO 2.2.0 Release Candidate 1 ##################
	
	public function upgrade2020051Step1()
	{
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{
			$table->addColumn('display_items_on_index', 'tinyint')->setDefault(1);
		});	
	}
	

	// ################################ UPGRADE TO 2.2.1 ##################
	
	public function upgrade2020170Step1()
	{	
		$this->alterTable('xf_xa_ims_item', function(Alter $table)
		{
			$table->changeColumn('item_state', 'enum')->values(['visible','moderated','deleted','awaiting','draft'])->setDefault('visible');
		});
	}	
	

	// ################################ UPGRADE TO 2.2.4 ##################
	
	public function upgrade2020470Step1()
	{
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('description');
			$table->addColumn('autopost_update', 'tinyint')->setDefault(0)->after('autopost_question');
			$table->addColumn('update_field_cache', 'mediumblob')->after('review_field_cache');
			$table->addColumn('allow_index', 'enum')->values(['allow', 'deny', 'criteria'])->setDefault('allow');
			$table->addColumn('index_criteria', 'blob');
		});
		
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{		
			$table->changeColumn('autopost_review', 'tinyint')->setDefault(0);
			$table->changeColumn('autopost_question', 'tinyint')->setDefault(0);
		});
		
		// UPDATE EXISTING CATEGORY RECORDS, Check to see if there is a thread_node_id set and if so, enabled the autoposts!
		$this->query("UPDATE xf_xa_ims_category SET autopost_update = 1 WHERE thread_node_id > 0");
	}

	public function upgrade2020470Step2()
	{	
		$this->alterTable('xf_xa_ims_item', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('update_count', 'int')->setDefault(0)->after('watch_count');
		});
		
		$this->alterTable('xf_xa_ims_item_page', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('description', 'varchar', 256)->setDefault('')->after('depth');
		});
	}
	
	public function upgrade2020470Step3()
	{
		$this->createTable('xf_xa_ims_item_update', function(Create $table)
		{
			$table->addColumn('item_update_id', 'int')->autoIncrement();
			$table->addColumn('item_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('title', 'varchar', 100)->setDefault('');
			$table->addColumn('update_date', 'int')->setDefault(0);
			$table->addColumn('edit_date', 'int')->setDefault(0);
			$table->addColumn('update_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('custom_fields', 'mediumblob');
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('reply_count', 'int')->setDefault(0);
			$table->addColumn('first_reply_date', 'int')->setDefault(0);
			$table->addColumn('last_reply_date', 'int')->setDefault(0);
			$table->addColumn('latest_reply_ids', 'blob');			
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey(['item_id', 'update_date']);
		});

		$this->createTable('xf_xa_ims_item_update_reply', function(Create $table)
		{
			$table->addColumn('reply_id', 'int')->autoIncrement();
			$table->addColumn('item_update_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50);
			$table->addColumn('reply_date', 'int');
			$table->addColumn('reply_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey(['item_update_id', 'reply_date']);
			$table->addKey('user_id');
			$table->addKey('reply_date');
		});
	}
	
	public function upgrade2020470Step4()
	{
		$this->createTable('xf_xa_ims_category_update_field', function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('category_id', 'int');
			$table->addPrimaryKey(['field_id', 'category_id']);
			$table->addKey('category_id');
		});
	
		$this->createTable('xf_xa_ims_update_field', function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('display_group', 'varchar', 25)->setDefault('above');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('field_type', 'varbinary', 25)->setDefault('textbox');
			$table->addColumn('field_choices', 'blob');
			$table->addColumn('match_type', 'varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob');
			$table->addColumn('max_length', 'int')->setDefault(0);
			$table->addColumn('required', 'tinyint')->setDefault(0);
			$table->addColumn('display_template', 'text');
			$table->addColumn('wrapper_template', 'text');
			$table->addColumn('editable_user_group_ids', 'blob');
			$table->addPrimaryKey('field_id');
			$table->addKey(['display_group', 'display_order'], 'display_group_order');
		});
	
		$this->createTable('xf_xa_ims_update_field_value', function(Create $table)
		{
			$table->addColumn('item_update_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['item_update_id', 'field_id']);
			$table->addKey('field_id');
		});
	}

	
	// ################################ UPGRADE TO 2.2.6 ##################
	
	public function upgrade2020670Step1()
	{
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{
			$table->changeColumn('content_image', 'varchar', 200);
		});
	
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{
			$table->renameColumn('content_image', 'content_image_url');
		});
	}
	
	
	
	// ################################ UPGRADE TO 2.2.7 ##################
	
	public function upgrade2020770Step1()
	{	
		$this->alterTable('xf_xa_ims_question_reply', function(Alter $table)
		{
			$table->addColumn('attach_count', 'int')->setDefault(0)->after('message');
		});
		
		$this->alterTable('xf_xa_ims_review_reply', function(Alter $table)
		{
			$table->addColumn('attach_count', 'int')->setDefault(0)->after('message');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.9 ##################
	
	public function upgrade2020970Step1()
	{
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{
			$table->addColumn('allow_self_join_contributors', 'tinyint')->setDefault(0)->after('allow_contributors');
			$table->addColumn('max_allowed_contributors', 'smallint')->setDefault(0)->after('allow_self_join_contributors');
		});
	
		// Set a default for max_allowed_contributors if allow_contrinutors is set
		$this->query("UPDATE xf_xa_ims_category SET max_allowed_contributors = 25 WHERE allow_contributors = 1");
	}
	
	
	
	// ################################ UPGRADE TO 2.2.11 ##################
	
	public function upgrade2021170Step1()
	{	
		$this->createTable('xf_xa_ims_series', function(Create $table)
		{
			$table->addColumn('series_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('title', 'varchar', 150);
			$table->addColumn('og_title', 'varchar', 100)->setDefault('');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('');
			$table->addColumn('description', 'mediumtext');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('');
			$table->addColumn('series_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('create_date', 'int')->setDefault(0);
			$table->addColumn('edit_date', 'int')->setDefault(0);
			$table->addColumn('last_feature_date', 'int')->setDefault(0);
			$table->addColumn('item_count', 'int')->setDefault(0);
			$table->addColumn('last_part_date', 'int')->setDefault(0);
			$table->addColumn('last_part_id', 'int')->setDefault(0);
			$table->addColumn('last_part_item_id', 'int')->setDefault(0);
			$table->addColumn('community_series', 'tinyint')->setDefault(0);
			$table->addColumn('icon_date', 'int')->setDefault(0);
			$table->addColumn('tags', 'mediumblob');
			$table->addColumn('attach_count', 'smallint', 5)->setDefault(0);
			$table->addColumn('has_poll', 'tinyint')->setDefault(0);
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0);
			$table->addColumn('reactions', 'blob')->nullable();
			$table->addColumn('reaction_users', 'blob');
			$table->addColumn('warning_id', 'int')->setDefault(0);
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('');
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey('title');
			$table->addKey('user_id');
		});

		$this->createTable('xf_xa_ims_series_feature', function(Create $table)
		{		
			$table->addColumn('series_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('series_id');
			$table->addKey('feature_date');
		});

		$this->createTable('xf_xa_ims_series_part', function(Create $table)
		{
			$table->addColumn('series_part_id', 'int')->autoIncrement();
			$table->addColumn('series_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('item_id', 'int');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('create_date', 'int')->setDefault(0);
			$table->addColumn('edit_date', 'int')->setDefault(0);
			$table->addKey('display_order');
			$table->addKey('user_id');
		});

		$this->createTable('xf_xa_ims_series_watch', function(Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('series_id', 'int');
			$table->addColumn('notify_on', 'enum')->values(['','series_part']);
			$table->addColumn('send_alert', 'tinyint');
			$table->addColumn('send_email', 'tinyint');
			$table->addPrimaryKey(['user_id', 'series_id']);
			$table->addKey(['series_id', 'notify_on'], 'node_id_notify_on');
		});
	}
	
	public function upgrade2021170Step2()
	{
		$this->alterTable('xf_xa_ims_item', function(Alter $table)
		{
			$table->addColumn('series_part_id', 'int')->setDefault(0)->after('page_count');
		});
	}
	
	public function upgrade2021170Step3()
	{	
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_ims_series_count', 'int')->setDefault(0)->after('xa_ims_review_count');
			$table->addKey('xa_ims_review_count', 'ims_review_count');
			$table->addKey('xa_ims_series_count', 'ims_series_count');
		});
	}
	
	
	
	// ################################ UPGRADE TO 2.2.12 ##################
	
	public function upgrade2021270Step1()
	{
		$this->createTable('xf_xa_ims_feed', function(Create $table)	
		{
			$table->addColumn('feed_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 250);
			$table->addColumn('url', 'varchar', 2083);
			$table->addColumn('frequency', 'int')->setDefault(1800);
			$table->addColumn('category_id', 'int');
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('prefix_id', 'int')->setDefault(0);
			$table->addColumn('title_template', 'varchar', 250)->setDefault('');
			$table->addColumn('message_template', 'mediumtext');
			$table->addColumn('item_visible', 'tinyint')->setDefault(1);
			$table->addColumn('last_fetch', 'int')->setDefault(0);
			$table->addColumn('active', 'int')->setDefault(0);
			$table->addKey('active');
		});

		$this->createTable('xf_xa_ims_feed_log', function(Create $table)
		{
			$table->addColumn('feed_id', 'int');
			$table->addColumn('unique_id', 'varbinary', 250);
			$table->addColumn('hash', 'char', 32)->comment('MD5(title + content)');
			$table->addColumn('item_id', 'int');
			$table->addPrimaryKey(['feed_id', 'unique_id']);
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.16 ##################
	
	public function upgrade2021670Step1()
	{
		$this->alterTable('xf_xa_ims_item_update_reply', function(Alter $table)
		{	
			$table->addColumn('last_edit_date', 'int')->setDefault(0)->after('warning_message');
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0)->after('last_edit_date');
			$table->addColumn('edit_count', 'int')->setDefault(0)->after('last_edit_user_id');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.17 ##################
	
	public function upgrade2021770Step1()
	{
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{
			$table->addColumn('require_location', 'tinyint')->setDefault(0)->after('allow_location');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.18 #################

	public function upgrade2021870Step1()
	{
		$this->alterTable('xf_xa_ims_item', function (Alter $table)
		{
			$table->dropColumns(['overview_page_nav_title']);
		});
	}
	
	public function upgrade2021870Step2()
	{
		$this->alterTable('xf_xa_ims_item_page', function (Alter $table)
		{
			$table->addColumn('cover_image_caption', 'varchar', 500)->setDefault('')->after('cover_image_id');
		});
		
		$this->alterTable('xf_xa_ims_item_page', function (Alter $table)
		{
			$table->dropColumns(['nav_title']);
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.21 #################
	
	public function upgrade2022170Step1()
	{	
		$this->createTable('xf_xa_ims_series_view', function(Create $table)
		{
			$table->addColumn('series_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('series_id');
		});
		
		$this->alterTable('xf_xa_ims_series', function (Alter $table)
		{
			$table->addColumn('view_count', 'int')->setDefault(0)->after('tags');
			$table->addColumn('watch_count', 'int')->setDefault(0)->after('view_count');
		});
	}	
	
	
	// ################################ UPGRADE TO 2.2.22 #################
	
	public function upgrade2022270Step1()
	{
		$widgetOptions = [
			'limit' => 10,
			'style' => 'list-view',
			'require_cover_or_content_image' => true
		];
		
		$this->insertNamedWidget('xa_ims_whats_new_overview_lastest_items', $widgetOptions);		
	}
	
	
	// ################################ UPGRADE TO 2.3.0 Alpha 1 ##################
	
	public function upgrade2030011Step1()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaImsAllowedFileExtensions\'
		');
	
		$attachmentExtensions = preg_split('/\s+/', trim($attachmentExtensions), -1, PREG_SPLIT_NO_EMPTY);
	
		if (!in_array('webp', $attachmentExtensions))
		{
			$newAttachmentExtensions = $attachmentExtensions;
			$newAttachmentExtensions[] = 'webp';
			$newAttachmentExtensions = implode("\n", $newAttachmentExtensions);
	
			$this->executeUpgradeQuery('
				UPDATE xf_option
				SET option_value = ?
				WHERE option_id = \'xaImsAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade2030011Step2(): void
	{
		$defaultEngine = \XF::config('db')['engine'] ?? 'InnoDb';
		if ($defaultEngine !== 'InnoDb')
		{
			\XF::logError('During upgrade to XenAddons Item Management System 2.3.0 we could not convert some tables to InnoDb: xf_xa_ims_item_view and xf_xa_ims_series_view');
			return;
		}
		
		$tables = [
			'xf_xa_ims_item_view',
			'xf_xa_ims_series_view'
		];
	
		foreach ($tables AS $tableName)
		{
			$this->alterTable($tableName, function (Alter $table)
			{
				$table->engine('InnoDB');
			});
		}
	}
	
	public function upgrade2030011Step3(): void
	{
		$this->alterTable('xf_xa_ims_series', function (Alter $table)
		{
			$table->addColumn('icon_optimized', 'tinyint')->setDefault(0)->after('icon_date');
		});
	}
	
	
	// ################################ UPGRADE TO 2.3.0 Release Candidate 5 ##################
	
	public function upgrade2030055Step1(): void
	{
		$this->insertNamedWidget('xa_ims_trending_items');
	}
	
	
	// ################################ UPGRADE TO 2.3.2 ##################
	
	public function upgrade2030270Step1()
	{
		$this->alterTable('xf_xa_ims_item', function(Alter $table)
		{
			$table->addColumn('is_private', 'tinyint')->setDefault(0)->after('message');
		});
	}	
	
	
	// ################################ UPGRADE TO 2.3.5 ##################
		
	public function upgrade2030570Step1()
	{
		$this->alterTable('xf_xa_ims_item', function(Alter $table)
		{
			$table->addColumn('disable_self_join_contributors', 'tinyint')->setDefault(0)->after('questions_open');
		});
	}	
	

	// ################################ UPGRADE TO 2.3.6 ##################

	public function upgrade2030670Step1()
	{
		$this->alterTable('xf_xa_ims_category', function(Alter $table)
		{
			$table->addColumn('allow_claims', 'tinyint')->setDefault(0)->after('allow_poll');
			$table->addColumn('allow_questions', 'tinyint')->setDefault(1)->after('allow_business_hours');
			$table->addColumn('question_reply_voting', 'varchar', 25)->setDefault('')->after('allow_questions');
		});
	}
	
	public function upgrade2030670Step2()
	{
		$this->alterTable('xf_xa_ims_item', function(Alter $table)
		{
			$table->addColumn('claim_date', 'int')->setDefault(0)->after('overview_page_title');
			$table->addColumn('claim_id', 'int')->setDefault(0)->after('claim_date');
		});
	}
	
	public function upgrade2030670Step3()
	{
		$this->alterTable('xf_xa_ims_question', function(Alter $table)
		{
			$table->addColumn('is_faq', 'tinyint')->setDefault(0)->after('question_state');
			$table->addKey(['is_faq', 'item_id']);
		});
	}
	
	public function upgrade2030670Step4()
	{	
		$this->createTable('xf_xa_ims_claim', function(Create $table)
		{
			$table->addColumn('claim_id', 'int')->autoIncrement();
			$table->addColumn('item_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('claim_date', 'int')->setDefault(0);
			$table->addColumn('edit_date', 'int')->setDefault(0);
			$table->addColumn('approve_date', 'int')->setDefault(0);
			$table->addColumn('reject_date', 'int')->setDefault(0);
			$table->addColumn('claim_status', 'enum')->values(['pending','approved','rejected'])->setDefault('pending');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('status_message', 'mediumtext');
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addUniqueKey(['item_id', 'user_id']);
			$table->addKey(['claim_id', 'claim_date'], 'claim_id_claim_date');
			$table->addKey('user_id');
			$table->addKey('claim_date');
			$table->addKey('claim_status');
		});
	}
	
	
	// ################################ UPGRADE TO 2.3.7 ##################

	public function upgrade2030770Step1()
	{
		$this->alterTable('xf_xa_ims_category', function (Alter $table)
		{
			$table->addColumn('auto_feature', 'tinyint')->setDefault(0)->after('display_items_on_index');
			$table->addColumn('featured_count', 'tinyint')->setDefault(0)->after('auto_feature');
		});
	}
	
	public function upgrade2030770Step2()
	{
		$this->alterTable('xf_xa_ims_item', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade2030770Step3()
	{
		$this->alterTable('xf_xa_ims_series', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade2030770Step4(): void
	{
		$db = $this->db();
	
		$featuredItems = $db->fetchAllKeyed(
			'SELECT item.*, item_feature.*
			FROM xf_xa_ims_item AS item
			INNER JOIN xf_xa_ims_item_feature AS item_feature
				ON (item_feature.item_id = item.item_id)
			ORDER BY item.item_id ASC',
				'item_id'
		);
	
		$rows = [];
		foreach ($featuredItems AS $itemId => $item)
		{
			$rows[] = [
				'content_type' => 'ims_item',
				'content_id' => $itemId,
				'content_container_id' => $item['category_id'],
				'content_user_id' => $item['user_id'],
				'content_username' => $item['username'],
				'content_date' => $item['create_date'],
				'content_visible' => ($item['item_state'] === 'visible'),
				'feature_user_id' => $item['user_id'],
				'feature_date' => $item['feature_date'],
				'snippet' => '',
			];
		}
	
		if (!empty($rows))
		{
			$db->beginTransaction();
	
			$db->insertBulk('xf_featured_content', $rows);
			$db->update(
				'xf_xa_ims_item',
				['featured' => 1],
				'item_id IN (' . $db->quote(array_keys($featuredItems)) . ')'
			);
	
			$db->commit();
		}
	}
	
	public function upgrade2030770Step5(): void
	{
		$db = $this->db();
	
		$featuredSeries = $db->fetchAllKeyed(
			'SELECT series.*, series_feature.*
			FROM xf_xa_ims_series AS series
			INNER JOIN xf_xa_ims_series_feature AS series_feature
				ON (series_feature.series_id = series.series_id)
			ORDER BY series.series_id ASC',
				'series_id'
		);
	
		$rows = [];
		foreach ($featuredSeries AS $seriesId => $series)
		{
			$rows[] = [
				'content_type' => 'ims_series',
				'content_id' => $seriesId,
				'content_container_id' => 0,
				'content_user_id' => $series['user_id'],
				'content_username' => $series['username'],
				'content_date' => $series['create_date'],
				'content_visible' => ($series['series_state'] === 'visible'),
				'feature_user_id' => $series['user_id'],
				'feature_date' => $series['feature_date'],
				'snippet' => '',
			];
		}
	
		if (!empty($rows))
		{
			$db->beginTransaction();
	
			$db->insertBulk('xf_featured_content', $rows);
			$db->update(
				'xf_xa_ims_series',
				['featured' => 1],
				'series_id IN (' . $db->quote(array_keys($featuredSeries)) . ')'
			);
	
			$db->commit();
		}
	}	
	
	public function upgrade2030770Step6(): void
	{
		$this->dropTable('xf_xa_ims_item_feature');
		
		$this->dropTable('xf_xa_ims_series_feature');
	}
	
	
	
	
	
	
	
	
	
	
	// ############################################ FINAL UPGRADE ACTIONS ##########################
	
	public function postUpgrade($previousVersion, array &$stateChanges)
	{
		if ($this->applyDefaultPermissions($previousVersion))
		{
			$this->app->jobManager()->enqueueUnique(
				'permissionRebuild',
				'XF:PermissionRebuild',
				[],
				false
			);
		}
		
		if ($previousVersion && $previousVersion < 2030670)
		{		
		
			// run job to rebuild IMS Questions so that any questions owned by the item owners or contributors get set as FAQs (is_faq)
			$this->app->jobManager()->enqueueUnique(
				'imsRebuildQuestions',
				'XenAddons\IMS:Question'
			);
		}

		// the following runs after every upgrade	
		\XF::repository('XenAddons\IMS:ItemPrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\IMS:ItemField')->rebuildFieldCache();
		\XF::repository('XenAddons\IMS:ReviewField')->rebuildFieldCache();
		\XF::repository('XenAddons\IMS:UpdateField')->rebuildFieldCache();

		$this->enqueuePostUpgradeCleanUp();
	}
	
	
	// ############################################ UNINSTALL STEPS #########################
	
	public function uninstallStep1()
	{
		$sm = $this->schemaManager();
	
		foreach (array_keys($this->getTables()) AS $tableName)
		{
			$sm->dropTable($tableName);
		}
	
		foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
		{
			$this->deleteWidget($widgetKey);
		}
	}
	
	public function uninstallStep2()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->dropColumns(['xa_ims_item_count', 'xa_ims_review_count', 'xa_ims_series_count']);
		});
	}
	
	public function uninstallStep3()
	{
		$db = $this->db();
	
		$contentTypes = [
			'ims_category', 
			'ims_claim',
			'ims_item', 
			'ims_page', 
			'ims_question', 
			'ims_question_reply', 
			'ims_review', 
			'ims_review_reply',
			'ims_series', 
			'ims_series_part',
			'ims_update',
			'ims_update_reply'
		];

		$this->uninstallContentTypeData($contentTypes);
		
		$db->beginTransaction();
	
		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'itemManagementSystem'");
		$db->delete('xf_permission_cache_content', "content_type = 'ims_category'");
		$db->delete('xf_permission_entry', "permission_group_id = 'xa_ims'");
		$db->delete('xf_permission_entry_content', "permission_group_id = 'xa_ims'");
	
		$db->commit();
	}
	
	
	// ############################# TABLE / DATA DEFINITIONS ##############################	
	
	protected function getTables(): array
	{
		$data = new MySql();
		return $data->getTables();
	}
	
	protected function getData(): array
	{
		$data = new MySql();
		return $data->getData();
	}	
	
	protected function getDefaultWidgetSetup()
	{
		return [
			'xa_ims_trending_items' => function ($key, array $options = [])
			{
				$options = array_replace([
					'contextual' => true,
					'style' => 'simple',
					'content_type' => 'ims_item',
				], $options);
					
				$this->createWidget(
					$key,
					'trending_content',
					[
						'positions' => [
							'xa_ims_index_sidenav' => 150,
							'xa_ims_category_sidenav' => 150,
						],
						'options' => $options,
					],
					'Trending items'
				);
			},		
			'xa_ims_latest_updates' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_ims_latest_updates',
					[
						'positions' => [
							'xa_ims_index_sidenav' => 200,
							'xa_ims_category_sidenav' => 200
						],
						'options' => $options
					]
				);
			},
			'xa_ims_latest_reviews' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_ims_latest_reviews',
					[
						'positions' => [
							'xa_ims_index_sidenav' => 300,
							'xa_ims_category_sidenav' => 300
						],
						'options' => $options
					]
				);
			},			
			'xa_ims_latest_questions' => function($key, array $options = [])
			{
				$options = array_replace([
					'limit' => 3,
					'style' => 'simple'
				], $options);
					
				$this->createWidget(
					$key,
					'xa_ims_latest_questions',
					[
						'positions' => [
							'xa_ims_index_sidenav' => 400,
							'xa_ims_category_sidenav' => 400
						],
						'options' => $options
					]
				);
			},
			'xa_ims_item_statistics' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_ims_item_statistics',
					[
						'positions' => ['xa_ims_index_sidenav' => 1000],
						'options' => $options
					]
				);
			},
			'xa_ims_whats_new_overview_lastest_items' => function($key, array $options = [])
			{
				$options = array_replace([
					'limit' => 10,
					'style' => 'list-view',
					'require_cover_or_content_image' => true
				], $options);
					
				$this->createWidget(
					$key,
					'xa_ims_latest_items',
					[
						'positions' => ['whats_new_overview' => 200],
						'options' => $options
					]
				);
			},			
		];
	}
	
	protected function insertNamedWidget($key, array $options = [])
	{
		$widgets = $this->getDefaultWidgetSetup();
		if (!isset($widgets[$key]))
		{
			throw new \InvalidArgumentException("Unknown widget '$key'");
		}
	
		$widgetFn = $widgets[$key];
		$widgetFn($key, $options);
	}
	
	protected function applyDefaultPermissions($previousVersion = null)
	{
		$applied = false;
	
		if (!$previousVersion)
		{
			// XenAddons\IMS: Item permissions
			$this->applyGlobalPermission('xa_ims', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewFull', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewItemAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewUpdates', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewUpdateAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewItemMap', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewIndexCategoryMaps', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'react', 'forum', 'react');
			$this->applyGlobalPermission('xa_ims', 'reactUpdate', 'forum', 'react');
			$this->applyGlobalPermission('xa_ims', 'updateReply', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'editUpdateReply', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ims', 'deleteUpdateReply', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermissionInt('xa_ims', 'editOwnReplyTimeLimit', -1);
			$this->applyGlobalPermission('xa_ims', 'add', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ims', 'addUpdate', 'forum', 'editOwnPost');
			$this->applyGlobalPermissionInt('xa_ims', 'maxItemCount', -1);
			$this->applyGlobalPermission('xa_ims', 'submitWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ims', 'uploadItemAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ims', 'uploadItemVideo', 'forum', 'postThread');
			$this->applyGlobalPermissionInt('xa_ims', 'maxAttachPerItem', -1);
			$this->applyGlobalPermission('xa_ims', 'addPageOwnItem', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ims', 'editOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ims', 'deleteOwn', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermissionInt('xa_ims', 'editOwnItemTimeLimit', -1);
			$this->applyGlobalPermission('xa_ims', 'tagOwnItem', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('xa_ims', 'tagAnyItem', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xa_ims', 'manageOthersTagsOwnItem', 'forum', 'manageOthersTagsOwnThread');
			$this->applyGlobalPermission('xa_ims', 'votePoll', 'forum', 'votePoll');
			$this->applyGlobalPermission('xa_ims', 'manageOwnContributors', 'forum', 'editOwnPost');
			
			// XenAddons\IMS: Item moderator permissions
			$this->applyGlobalPermission('xa_ims', 'inlineMod', 'forum', 'inlineMod');
			$this->applyGlobalPermission('xa_ims', 'viewModerated', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ims', 'viewDeleted', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ims', 'viewDraft', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ims', 'approveUnapprove', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ims', 'editAny', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ims', 'deleteAny', 'forum', 'deleteAnyPost');						
			$this->applyGlobalPermission('xa_ims', 'hardDeleteAny', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ims', 'undelete', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ims', 'manageAnyTag', 'forum', 'manageAnyTag');
			$this->applyGlobalPermission('xa_ims', 'featureUnfeature', 'forum', 'stickUnstickThread');
			$this->applyGlobalPermission('xa_ims', 'warn', 'forum', 'warn');
			$this->applyGlobalPermission('xa_ims', 'itemReplyBan', 'forum', 'threadReplyBan');
			$this->applyGlobalPermission('xa_ims', 'convertToThread', 'forum', 'manageAnyThread');
			
			// XenAddons\IMS: Question permissions			
			$this->applyGlobalPermission('xa_ims', 'viewQuestions', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewQuestionAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'reactQuestion', 'forum', 'react');
			$this->applyGlobalPermission('xa_ims', 'contentVoteAnswer', 'forum', 'react');
			$this->applyGlobalPermission('xa_ims', 'addQuestion', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'adQuestionWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ims', 'questionReply', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'questionReplyOwnItem', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'questionReplyOwnQuestion', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'uploadQuestionAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'uploadQuestionVideo', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'editQuestion', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ims', 'deleteQuestion', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermissionInt('xa_ims', 'editOwnQuestionTimeLimit', -1);
			
			// XenAddons\IMS: Question moderator permissions
			$this->applyGlobalPermission('xa_ims', 'inlineModQuestion', 'forum', 'inlineMod');
			$this->applyGlobalPermission('xa_ims', 'viewModeratedQuestions', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ims', 'viewDeletedQuestions', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ims', 'approveUnapproveQuestion', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ims', 'editAnyQuestion', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ims', 'deleteAnyQuestion', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ims', 'undeleteQuestion', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ims', 'hardDeleteAnyQuestion', 'forum', 'hardDeleteAnyPost');						
			$this->applyGlobalPermission('xa_ims', 'warnQuestion', 'forum', 'warn');			
			
			// XenAddons\IMS: Review permissions	
			$this->applyGlobalPermission('xa_ims', 'viewReviews', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewReviewAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'reactReview', 'forum', 'react');
			$this->applyGlobalPermission('xa_ims', 'contentVote', 'forum', 'react');
			$this->applyGlobalPermission('xa_ims', 'addReview', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'addReviewOwn', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'addReviewWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ims', 'reviewReply', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'reviewReplyOwnItem', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'reviewReplyOwnReview', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'uploadReviewAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'uploadReviewVideo', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'editReview', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ims', 'deleteReview', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermissionInt('xa_ims', 'editOwnReviewTimeLimit', -1);
			
			// Note: Deliberately not adding "Review items anonymously" or "Review items multiple times" permissions as these are expected to be set manually as part of post install configuration steps. 
			
			// XenAddons\IMS: Review moderator permissions
			$this->applyGlobalPermission('xa_ims', 'inlineModReview', 'forum', 'inlineMod');
			$this->applyGlobalPermission('xa_ims', 'viewModeratedReviews', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ims', 'viewDeletedReviews', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ims', 'approveUnapproveReview', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ims', 'editAnyReview', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ims', 'deleteAnyReview', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ims', 'undeleteReview', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ims', 'hardDeleteAnyReview', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ims', 'warnReview', 'forum', 'warn');	
			
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2020770)
		{
			// XenAddons\IMS: Question permissions
			$this->applyGlobalPermission('xa_ims', 'viewQuestionReplyAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'uploadQuestionReplyAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'uploadQuestionReplyVideo', 'forum', 'postReply');
			
			// XenAddons\IMS: Review permissions
			$this->applyGlobalPermission('xa_ims', 'viewReviewReplyAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'uploadReviewReplyAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ims', 'uploadReviewReplyVideo', 'forum', 'postReply');
			
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2021170)
		{		
			// XenAddons\IMS: Series permissions
			$this->applyGlobalPermission('xa_ims', 'viewSeries', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'viewSeriesAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ims', 'reactSeries', 'forum', 'react');
			$this->applyGlobalPermission('xa_ims', 'createSeries', 'forum', 'postThread');
			$this->applyGlobalPermissionInt('xa_ims', 'maxSeriesCount', -1);
			$this->applyGlobalPermission('xa_ims', 'addSeriesWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ims', 'uploadSeriesAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ims', 'uploadSeriesVideo', 'forum', 'postThread');
			$this->applyGlobalPermissionInt('xa_ims', 'maxAttachPerSeries', -1);
			$this->applyGlobalPermission('xa_ims', 'editOwnSeries', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ims', 'deleteOwnSeries', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermissionInt('xa_ims', 'editOwnSeriesTimeLimit', -1);
			$this->applyGlobalPermission('xa_ims', 'tagOwnSeries', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('xa_ims', 'tagAnySeries', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xa_ims', 'manageOthersTagsOwnSeries', 'forum', 'manageOthersTagsOwnThread');
			$this->applyGlobalPermission('xa_ims', 'votePollSeries', 'forum', 'votePoll');
			$this->applyGlobalPermission('xa_ims', 'addToCommunitySeries', 'forum', 'postThread');			
			
			// XenAddons\IMS: Series moderator permissions
			$this->applyGlobalPermission('xa_ims', 'inlineModSeries', 'forum', 'inlineMod');
			$this->applyGlobalPermission('xa_ims', 'viewModeratedSeries', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ims', 'viewDeletedSeries', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ims', 'approveUnapproveSeries', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ims', 'editAnySeries', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ims', 'deleteAnySeries', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ims', 'hardDeleteAnySeries', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ims', 'undeleteSeries', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ims', 'manageAnySeriesTag', 'forum', 'manageAnyTag');
			$this->applyGlobalPermission('xa_ims', 'featureUnfeatureSeries', 'forum', 'stickUnstickThread');
			$this->applyGlobalPermission('xa_ims', 'warnSeries', 'forum', 'warn');
				
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2021870)
		{
			// XenAddons\IMS: Item permissions
			$this->applyGlobalPermission('xa_ims', 'manageSeoOptionsOwnItem', 'forum', 'postThread');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 2030270)
		{
			// XenAddons\IMS: Item permissions
			$this->applyGlobalPermission('xa_ims', 'setPrivateOwn', 'forum', 'postThread');
			
			// XenAddons\IMS: Item moderator permissions
			$this->applyGlobalPermission('xa_ims', 'viewPrivate', 'forum', 'viewModerated');
		
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2030670)
		{		
			// XenAddons\IMS: Item permissions
			$this->applyGlobalPermission('xa_ims', 'claim', 'xa_ims', 'add');
			
			// XenAddons\IMS: Item moderator permissions
			$this->applyGlobalPermission('xa_ims', 'manageAnyClaim', 'xa_ims', 'approveUnapprove');
			
			// XenAddons\IMS: Question permissions
			$this->applyGlobalPermission('xa_ims', 'addQuestionOwnItem', 'xa_ims', 'add');
			
			// XenAddons\IMS: Question moderator permissions
			$this->applyGlobalPermission('xa_ims', 'reassignQuestion', 'xa_ims', 'editAnyQuestion');
			
			$applied = true;
		}
				
		return $applied;
	}	
}