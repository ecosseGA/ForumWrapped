<?php

namespace XenAddons\Showcase;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\Widget;
use XF\Service\RebuildNestedSet;
use XenAddons\Showcase\Install\Data\MySql;

use XenAddons\Showcase\Repository\ItemField;
use XenAddons\Showcase\Repository\ItemPrefix;

use function in_array, intval;

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
			$table->addColumn('xa_sc_item_count', 'int')->setDefault(0);
			$table->addColumn('xa_sc_comment_count', 'int')->setDefault(0);
			$table->addColumn('xa_sc_review_count', 'int')->setDefault(0);
			$table->addColumn('xa_sc_series_count', 'int')->setDefault(0);
			$table->addKey('xa_sc_item_count', 'sc_item_count');
			$table->addKey('xa_sc_comment_count', 'sc_comment_count');
			$table->addKey('xa_sc_review_count', 'sc_review_count');
			$table->addKey('xa_sc_series_count', 'sc_series_count');
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
		
		$this->insertThreadType('sc_item', 'XenAddons\Showcase:Item', 'XenAddons/Showcase');
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
		$service = \XF::service('XF:RebuildNestedSet', 'XenAddons\Showcase:Category', [
			'parentField' => 'parent_category_id'
		]);
		$service->rebuildNestedSetInfo();
	
		\XF::repository('XenAddons\Showcase:ItemPrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\Showcase:ItemField')->rebuildFieldCache();
		\XF::repository('XenAddons\Showcase:ReviewField')->rebuildFieldCache();
		\XF::repository('XenAddons\Showcase:UpdateField')->rebuildFieldCache();
	}
	
	
	// ################################ UPGRADE STEPS ####################
	

	// ################################ UPGRADE TO 2.2.0 Beta 1  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2020031Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_showcase_custom_field
				ADD is_searchable tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
				ADD fs_description varchar(250) NOT NULL default ''
		");
		
		$this->query("ALTER TABLE xf_nflj_showcase_category ADD default_prefix_id int(10) UNSIGNED NOT NULL DEFAULT '0' AFTER prefix_cache");
	}

	// ################################ UPGRADE TO 2.2.1  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2020170Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_item ADD xmg_album_id int(10) UNSIGNED NOT NULL DEFAULT '0'");
	}

	// ################################ UPGRADE TO 2.3.0 Beta 1  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2030031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_category ADD display_featured_items tinyint(3) UNSIGNED NOT NULL DEFAULT '1'");
		$this->query("ALTER TABLE xf_nflj_showcase_custom_field ADD COLUMN display_on_list tinyint(3) unsigned NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_user_option ADD COLUMN showcase_unviewed_items_count TINYINT(3) NOT NULL DEFAULT '1'");
	}

	// ################################ UPGRADE TO 2.3.0 Beta 3  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2030033Step1()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_showcase_review_field (
				field_id varchar(25) NOT NULL,
				display_group varchar(25) NOT NULL DEFAULT 'user_review',
				display_order int(10) unsigned NOT NULL DEFAULT '1',
				display_in_block tinyint(3) unsigned NOT NULL DEFAULT '0',
				field_type varchar(25) NOT NULL DEFAULT 'textbox',
				field_choices blob NOT NULL,
				match_type varchar(25) NOT NULL DEFAULT 'none',
				match_regex varchar(250) NOT NULL DEFAULT '',
				match_callback_class varchar(75) NOT NULL DEFAULT '',
				match_callback_method varchar(75) NOT NULL DEFAULT '',
				max_length int(10) unsigned NOT NULL DEFAULT '0',
				required tinyint(3) unsigned NOT NULL DEFAULT '0',
				display_template text NOT NULL,
				PRIMARY KEY (field_id),
				KEY display_group_order (display_group,display_order)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_showcase_review_field_category (
				field_id varbinary(25) NOT NULL,
				category_id int(11) NOT NULL,
				PRIMARY KEY (field_id,category_id),
				KEY category_id (category_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_showcase_review_field_value (
				rate_review_id int(10) unsigned NOT NULL,
				field_id varchar(25) NOT NULL,
				field_value mediumtext NOT NULL,
				PRIMARY KEY (rate_review_id,field_id),
				KEY field_id (field_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->query("ALTER TABLE xf_nflj_showcase_rate_review ADD custom_review_fields mediumblob NOT NULL");

		$this->query("
			ALTER TABLE xf_nflj_showcase_category
				ADD review_field_cache mediumblob NOT NULL AFTER field_cache,
				ADD items_per_page int(10) unsigned NOT NULL DEFAULT '0'
		");
	}
	
	// ################################ UPGRADE TO 2.4.0 Beta 1  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2040031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_category ADD allow_anon_reviews tinyint(3) unsigned NOT NULL DEFAULT '0'");

		$this->query("
			ALTER TABLE xf_nflj_showcase_item
				ADD author_rating float unsigned NOT NULL DEFAULT '0',
	
				CHANGE view_count
					item_view_count int(10) unsigned NOT NULL DEFAULT '0',
	
				ADD INDEX category_author_rating (category_id,author_rating),
				ADD INDEX author_rating (author_rating)
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_review_field_value
				ADD item_id int(10) unsigned NOT NULL AFTER rate_review_id,

				ADD INDEX itemId_fieldId (item_id,field_id),
				ADD INDEX item_id (item_id)
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_rate_review
				ADD warning_id INT UNSIGNED NOT NULL DEFAULT 0,
				ADD is_anonymous TINYINT UNSIGNED NOT NULL DEFAULT 0
		");
	}

	// ################################ UPGRADE TO 2.4.0 Beta 3  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2040033Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_custom_field ADD allow_view_field_user_group_ids blob NOT NULL");
		$this->query("ALTER TABLE xf_nflj_showcase_review_field ADD allow_view_field_user_group_ids blob NOT NULL");
		$this->query("UPDATE xf_nflj_showcase_custom_field SET allow_view_field_user_group_ids = '-1'");
		$this->query("UPDATE xf_nflj_showcase_review_field SET allow_view_field_user_group_ids = '-1'");
	}
		
	// ################################ UPGRADE TO 2.5.0 Beta 1  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2050031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_item ADD tags MEDIUMBLOB NOT NULL");

		$this->query("
			ALTER TABLE xf_nflj_showcase_category
				ADD allow_pros_cons tinyint(3) unsigned NOT NULL DEFAULT '0',
				ADD allow_author_rating tinyint(3) unsigned NOT NULL DEFAULT '0',
				ADD min_tags SMALLINT UNSIGNED NOT NULL DEFAULT '0'
		");
	
		// update all categories that are set to Rate & Review to set the allow_pros_cons field to 1 (enabled)
		$this->query("UPDATE xf_nflj_showcase_category SET allow_pros_cons = 1 WHERE rate_review_system = 2");
		$this->query("ALTER TABLE xf_nflj_showcase_item DROP COLUMN item_tags");
	}
		
	// ################################ UPGRADE TO 2.5.2  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2050270Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_category ADD category_options MEDIUMBLOB NOT NULL");
	}
		
	// ################################ UPGRADE TO 2.5.3  ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2050370Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_showcase_item
				ADD item_open tinyint(3) unsigned NOT NULL DEFAULT '1' AFTER item_state,
				ADD comments_open tinyint(3) unsigned NOT NULL DEFAULT '1',
				ADD last_edit_date int(10) unsigned NOT NULL DEFAULT '0',
				ADD last_edit_user_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD edit_count int(10) unsigned NOT NULL DEFAULT '0'
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_category
				CHANGE category_name category_name varchar(100) NOT NULL,
				DROP COLUMN require_item_image
		");
	}
		
	// ################################ UPGRADE TO 2.5.4 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2050431Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_rate_review ADD attach_count int(10) unsigned NOT NULL DEFAULT '0' ");
	}

	// ################################ UPGRADE TO 2.5.5 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2050531Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_showcase_comment_reply
				ADD comment_reply_state enum('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
				ADD likes int(10) unsigned NOT NULL DEFAULT '0',
				ADD like_users blob NOT NULL,
				ADD warning_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD warning_message varchar(255) NOT NULL DEFAULT ''
		");
	}

	// ################################ UPGRADE TO 2.6.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2060031Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_showcase_item
				ADD map_location text NOT NULL
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_category
				ADD modular_layout_options mediumblob NOT NULL,
				ADD modular_home_limit INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD modular_cat_limit INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD allow_maps tinyint(3) unsigned NOT NULL DEFAULT '1',
	
				DROP category_layout_override,
				DROP category_layout_type
		");
	}

	// ################################ UPGRADE TO 2.6.3 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2060370Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_item ADD cover_image_cache BLOB NOT NULL");
	}
	
	// ################################ UPGRADE TO 2.6.5 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2060570Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_showcase_item
				ADD image_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD file_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0'
		");
	}
		
	// ################################ UPGRADE TO 2.7.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2070031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_category ADD item_image_required tinyint(3) unsigned NOT NULL DEFAULT '1'");
	}
		
	// ################################ UPGRADE TO 2.8.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2080031Step1()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_showcase_item_reply_ban (
				item_reply_ban_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				item_id int(10) unsigned NOT NULL,
				user_id int(10) unsigned NOT NULL,
				ban_date int(10) unsigned NOT NULL,
				expiry_date int(10) unsigned DEFAULT NULL,
				reason varchar(100) NOT NULL DEFAULT '',
				ban_user_id int(10) unsigned NOT NULL,
				PRIMARY KEY (item_reply_ban_id),
				UNIQUE KEY item_id_user_id (item_id,user_id),
				KEY expiry_date (expiry_date),
				KEY user_id (user_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_item
				CHANGE xmg_album_id xfmg_album_id int(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD xfmg_media_ids varbinary(100) NOT NULL DEFAULT '' AFTER xfmg_album_id,
				ADD xfmg_video_ids varbinary(100) NOT NULL DEFAULT '' AFTER xfmg_media_ids,
				ADD warning_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD warning_message varchar(255) NOT NULL DEFAULT '',
				ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_custom_field
				ADD is_filter_link tinyint(3) unsigned NOT NULL DEFAULT '0' AFTER is_searchable
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_comment
				ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD attach_count int(10) unsigned NOT NULL DEFAULT '0'
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_rate_review
				ADD warning_message varchar(255) NOT NULL DEFAULT '' AFTER warning_id,
				ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER warning_message,
				ADD INDEX item_id_user_id (item_id,user_id),
				ADD INDEX user_id (user_id)
		");

		$this->query("ALTER TABLE xf_nflj_showcase_rate_review DROP INDEX unique_rating");

		$this->query("
			ALTER TABLE xf_nflj_showcase_category
				DROP gallery_options_override,
				DROP gallery_type,
				DROP gallery_tab1
		");

	}
		
	// ################################ UPGRADE TO 2.8.1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2080170Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_rate_review ADD username varchar(50) NOT NULL AFTER user_id");
	}
		
	// ################################ UPGRADE TO 2.9.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2090031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_item ADD map_location_data mediumblob NOT NULL AFTER map_location");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_showcase_review_reply (
				review_reply_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				rate_review_id int(10) unsigned NOT NULL,
				user_id int(10) unsigned NOT NULL,
				username varchar(50) NOT NULL,
				review_reply_date int(10) unsigned NOT NULL,
				review_reply_state enum('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
				message mediumtext NOT NULL,
				likes int(10) unsigned NOT NULL DEFAULT '0',
				like_users blob NOT NULL,
				warning_id int(10) unsigned NOT NULL DEFAULT '0',
				warning_message varchar(255) NOT NULL DEFAULT '',
				ip_id int(10) unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (review_reply_id),
				KEY user_id (user_id),
				KEY rate_review_id_review_reply_date (rate_review_id,review_reply_date),
				KEY review_reply_date (review_reply_date)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8
		");

		$this->query("
			ALTER TABLE xf_nflj_showcase_rate_review
				ADD review_reply_count int(10) unsigned NOT NULL DEFAULT '0',
				ADD first_review_reply_date int(10) unsigned NOT NULL DEFAULT '0',
				ADD last_review_reply_date int(10) unsigned NOT NULL DEFAULT '0',
				ADD latest_review_reply_ids varbinary(100) NOT NULL DEFAULT ''
		");
	}

	// ################################ UPGRADE TO 2.9.0 RC 2 ##################
	// note: this is just translated from the XF1 version roughly as is

	public function upgrade2090052Step1()
	{
		$this->query("ALTER TABLE xf_nflj_showcase_rate_review ADD warning_message varchar(255) NOT NULL DEFAULT '' AFTER warning_id");
	}	
	
	
	// ################################ START OF XF2 VERSION OF SHOWCASE ##################	
	
	
	// ################################ UPGRADE TO 3.0.0 Alpha 1 ##################
	
	public function upgrade3000011Step1()
	{
		$sm = $this->schemaManager();
	
		$renameTables = [
			'xf_nflj_showcase_category' => 'xf_xa_sc_category',
			'xf_nflj_showcase_category_prefix' => 'xf_xa_sc_category_prefix',
			'xf_nflj_showcase_category_watch' => 'xf_xa_sc_category_watch',
			'xf_nflj_showcase_comment' => 'xf_xa_sc_comment',
			'xf_nflj_showcase_comment_reply' => 'xf_xa_sc_comment_reply',
			'xf_nflj_showcase_custom_field' => 'xf_xa_sc_item_field',
			'xf_nflj_showcase_custom_field_category' => 'xf_xa_sc_item_field_category',
			'xf_nflj_showcase_custom_field_value' => 'xf_xa_sc_item_field_value',
			'xf_nflj_showcase_item' => 'xf_xa_sc_item',
			'xf_nflj_showcase_item_read' => 'xf_xa_sc_item_read',
			'xf_nflj_showcase_item_reply_ban' => 'xf_xa_sc_item_reply_ban',
			'xf_nflj_showcase_item_view' => 'xf_xa_sc_item_view',
			'xf_nflj_showcase_item_watch' => 'xf_xa_sc_item_watch',			
			'xf_nflj_showcase_prefix' => 'xf_xa_sc_item_prefix',
			'xf_nflj_showcase_prefix_group' => 'xf_xa_sc_item_prefix_group',
			'xf_nflj_showcase_rate_review' => 'xf_xa_sc_item_rating',
			'xf_nflj_showcase_review_field' => 'xf_xa_sc_review_field',
			'xf_nflj_showcase_review_field_category' => 'xf_xa_sc_review_field_category',
			'xf_nflj_showcase_review_field_value' => 'xf_xa_sc_review_field_value',
			'xf_nflj_showcase_review_reply' => 'xf_xa_sc_item_rating_reply',
		];
		foreach ($renameTables AS $from => $to)
		{
			$sm->renameTable($from, $to);
		}
	
		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->renameColumn('showcase_count', 'xa_sc_item_count');
		});
		
		$this->schemaManager()->alterTable('xf_user_option', function(Alter $table)
		{
			$table->dropColumns('showcase_unviewed_items_count');
		});
	}
	
	public function upgrade3000011Step2()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->renameColumn('category_name', 'title');
			$table->renameColumn('category_description', 'description');
			$table->renameColumn('category_image', 'content_image');
			$table->renameColumn('category_content', 'content_message');
			$table->renameColumn('category_content_title', 'content_title');
			$table->renameColumn('last_item', 'last_item_date');
			$table->renameColumn('tab1_name', 'title_s1');
			$table->renameColumn('tab2_name', 'title_s2');
			$table->renameColumn('tab3_name', 'title_s3');
			$table->renameColumn('tab4_name', 'title_s4');
			$table->renameColumn('tab5_name', 'title_s5');
			$table->renameColumn('tab1_description', 'description_s1');
			$table->renameColumn('tab2_description', 'description_s2');
			$table->renameColumn('tab3_description', 'description_s3');
			$table->renameColumn('tab4_description', 'description_s4');
			$table->renameColumn('tab5_description', 'description_s5');
			$table->renameColumn('tab2_editor', 'editor_s2');
			$table->renameColumn('tab3_editor', 'editor_s3');
			$table->renameColumn('tab4_editor', 'editor_s4');
			$table->renameColumn('tab5_editor', 'editor_s5');
			$table->renameColumn('rate_review_system', 'allow_ratings');  
			$table->renameColumn('review_required', 'require_review');
			$table->renameColumn('category_style_id', 'style_id');
			$table->renameColumn('category_breadcrumb', 'breadcrumb_data');
			$table->renameColumn('allow_maps', 'allow_location');
			$table->renameColumn('item_image_required', 'require_item_image');
			
			$table->addColumn('featured_count', 'smallint')->setDefault(0)->after('item_count');
			$table->addColumn('layout_type', 'varchar', 25)->setDefault('');
				
			$table->dropColumns(['tab1_editor', 'display_featured_items', 'items_per_page', 'category_options', 'modular_layout_options', 'modular_home_limit', 'modular_cat_limit']);
		});
		
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->changeColumn('editor_s2', 'tinyint', 3)->setDefault(0);
			$table->changeColumn('editor_s3', 'tinyint', 3)->setDefault(0);
			$table->changeColumn('editor_s4', 'tinyint', 3)->setDefault(0);
			$table->changeColumn('editor_s5', 'tinyint', 3)->setDefault(0);
		});
		
		$this->query("UPDATE xf_xa_sc_category SET allow_ratings = 1 WHERE allow_ratings = 2");
	}
		
	public function upgrade3000011Step3()
	{
		$sm = $this->schemaManager();		
		
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->dropColumns(['item_open', 'featured', 'cover_image_cache', 'image_attach_count', 'file_attach_count']);
		});
	
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->renameColumn('item_name', 'title');
			$table->renameColumn('message_t2', 'message_s2');
			$table->renameColumn('message_t3', 'message_s3');
			$table->renameColumn('message_t4', 'message_s4');
			$table->renameColumn('message_t5', 'message_s5');
			$table->renameColumn('date_added', 'create_date');
			$table->renameColumn('date_edited', 'edit_date');
			$table->renameColumn('item_view_count', 'view_count');
			$table->renameColumn('map_location', 'location');
			$table->renameColumn('map_location_data', 'location_data');
			$table->renameColumn('custom_item_fields', 'custom_fields');
			$table->renameColumn('thread_id', 'discussion_thread_id');
	
			$table->changeColumn('likes', 'int')->setDefault(0);
		});
	
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('last_comment_id', 'int')->setDefault(0)->after('last_comment_date');
			$table->addColumn('last_comment_user_id', 'int')->setDefault(0)->after('last_comment_id');
			$table->addColumn('last_comment_username', 'varchar', 50)->setDefault('')->after('last_comment_user_id');
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});
		
		$sm->alterTable('xf_xa_sc_item_watch', function(Alter $table)
		{
			$table->dropColumns('watch_key');
		});
	}

	public function upgrade3000011Step4()
	{
		$sm = $this->schemaManager();	
	
		$sm->alterTable('xf_xa_sc_comment', function(Alter $table)
		{
			$table->renameColumn('comment_reply_count', 'reply_count');
			$table->renameColumn('first_comment_reply_date', 'first_reply_date');
			$table->renameColumn('last_comment_reply_date', 'last_reply_date');
			$table->renameColumn('latest_comment_reply_ids', 'latest_reply_ids');
				
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
	
			$table->changeColumn('like_users', 'blob')->nullable(true);
		});
	
		$sm->createTable('xf_xa_sc_comment_read', function(Create $table)
		{
			$table->addColumn('comment_read_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('item_id', 'int');
			$table->addColumn('comment_read_date', 'int');
			$table->addUniqueKey(['user_id', 'item_id']);
			$table->addKey('item_id');
			$table->addKey('comment_read_date');
		});
	}
	
	public function upgrade3000011Step5()
	{
		$sm = $this->schemaManager();
			
		$sm->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->changeColumn('likes', 'int')->setDefault(0);
			$table->changeColumn('like_users', 'blob')->nullable(true);
			$table->changeColumn('custom_review_fields', 'mediumblob')->nullable(true);
			
			$table->dropColumns(['latest_reply_ids']);
		});
		
		$sm->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->renameColumn('rate_review_id', 'rating_id');
			$table->renameColumn('rate_review_date', 'rating_date');
			$table->renameColumn('rate_review_state', 'rating_state');
			$table->renameColumn('pros_message', 'pros');
			$table->renameColumn('cons_message', 'cons');
			$table->renameColumn('review_title', 'title_legacy');
			$table->renameColumn('summary_message', 'message');
			$table->renameColumn('owner_reply', 'author_response');
			$table->renameColumn('custom_review_fields', 'custom_fields');
			$table->renameColumn('review_reply_count', 'reply_count');
			$table->renameColumn('first_review_reply_date', 'first_reply_date');
			$table->renameColumn('last_review_reply_date', 'last_reply_date');
	
			$table->addColumn('latest_reply_ids', 'blob');
			$table->addColumn('embed_metadata', 'blob')->nullable();
				
			$table->addKey(['item_id', 'rating_date']);
			$table->addKey(['user_id']);
	
			$table->dropIndexes('item_id');
		});
		
		$sm->alterTable('xf_xa_sc_item_rating_reply', function(Alter $table)
		{
			$table->renameColumn('review_reply_id', 'reply_id');
			$table->renameColumn('rate_review_id', 'rating_id');
			$table->renameColumn('review_reply_date', 'reply_date');
			$table->renameColumn('review_reply_state', 'reply_state');
			
			$table->changeColumn('likes', 'int')->setDefault(0);
			$table->changeColumn('like_users', 'blob')->nullable(true);
			
			$table->dropIndexes(['rate_review_id_review_reply_date', 'review_reply_date']);
		});
		
		$sm->alterTable('xf_xa_sc_item_rating_reply', function(Alter $table)
		{		
			$table->addKey(['rating_id', 'reply_date']);
			$table->addKey(['reply_date']);
		});
	}
	
	// update item rating reply caches
	public function upgrade3000011Step6($stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
	
		$perPage = 250;
	
		$db = $this->db();
	
		$itemRatingIds = $db->fetchAllColumn($db->limit(
			'
				SELECT rating_id
				FROM xf_xa_sc_item_rating
				WHERE rating_id > ?
					AND reply_count > 0
				ORDER BY rating_id
			', $perPage
		), $stepParams['position']);
		if (!$itemRatingIds)
		{
			return true;
		}
	
		$db->beginTransaction();
	
		$itemRatings = $db->fetchAllKeyed('
			SELECT *
			FROM xf_xa_sc_item_rating
			WHERE rating_id IN(' . $db->quote($itemRatingIds) . ')
		', 'rating_id');
	
		foreach ($itemRatings AS $itemRatingId => $itemRating)
		{
			$latestReplyIds = @unserialize($itemRating['latest_reply_ids']);
			if (is_array($latestReplyIds))
			{
				continue; // already in new format
			}
	
			$data = $db->fetchAllKeyed($db->limit('
				SELECT reply_id, reply_state, user_id
				FROM xf_xa_sc_item_rating_reply
				WHERE rating_id = ?
				ORDER BY reply_date DESC
			', 20), 'reply_id', $itemRatingId);
	
			$cache = [];
			$visibleCount = 0;
	
			foreach ($data AS $id => $row)
			{
				$cache[$id] = [$row['reply_state'], $row['user_id']];
				if ($row['reply_state'] == 'visible')
				{
					$visibleCount++;
				}
	
				if ($visibleCount === 3)
				{
					break;
				}
			}
			$cache = array_reverse($cache, true); // need last replies, but in oldest first order
	
			$db->update('xf_xa_sc_item_rating', ['latest_reply_ids' => serialize($cache)], 'rating_id = ?', $itemRatingId);
		}
	
		$db->commit();
		
		$stepParams['position'] = end($itemRatingIds);
		
		return $stepParams;
	}	
	
	public function upgrade3000011Step7()
	{
		$sm = $this->schemaManager();
	
		$sm->dropTable('xf_xa_sc_item_view');
	
		$sm->createTable('xf_xa_sc_item_view', function(Create $table)
		{
			$table->addColumn('item_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('item_id');
		});
	}	
	
	public function upgrade3000011Step8()
	{
		$sm = $this->schemaManager();
		
		$sm->createTable('xf_xa_sc_item_feature', function(Create $table)
		{
			$table->addColumn('item_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('item_id');
			$table->addKey('feature_date');
		});
	}		
	
	public function upgrade3000011Step9(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
	
		$perPage = 250;
	
		$db = $this->db();
	
		$commentReplyIds = $db->fetchAllColumn($db->limit(
			'
				SELECT comment_reply_id
				FROM xf_xa_sc_comment_reply
				WHERE comment_reply_id > ?
				ORDER BY comment_reply_id
			', $perPage
		), $stepParams['position']);
		
		if (!$commentReplyIds)
		{
			return true;
		}
	
		$commentReplies = $db->fetchAll('
			SELECT comment_reply.*,
				comment.item_id, comment.message as comment_message, comment.user_id as comment_user_id, comment.username as comment_username
			FROM xf_xa_sc_comment_reply AS comment_reply
			INNER JOIN xf_xa_sc_comment as comment
				ON (comment_reply.comment_id = comment.comment_id)
			WHERE comment_reply.comment_reply_id IN (' . $db->quote($commentReplyIds) . ')
		');
	
		$db->beginTransaction();
	
		foreach ($commentReplies AS $commentReply)
		{
			$quotedComment = $this->getQuoteWrapper($commentReply);
			$message = $quotedComment . $commentReply['message'];
	
			$this->db()->query("
				INSERT INTO xf_xa_sc_comment
					(item_id, user_id, username, comment_date, comment_state, message, likes, like_users, warning_id, warning_message, ip_id)
				VALUES
					(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			", array($commentReply['item_id'], $commentReply['user_id'], $commentReply['username'], $commentReply['comment_reply_date'],
				$commentReply['comment_reply_state'], $message, $commentReply['likes'], $commentReply['like_users'],
				$commentReply['warning_id'], $commentReply['warning_message'], $commentReply['ip_id']));
		}
	
		$db->commit();
	
		$stepParams['position'] = end($commentReplyIds);
	
		return $stepParams;
	}
	
	public function getQuoteWrapper($commentReply)
	{
		return '[QUOTE="'
			. $commentReply['comment_username']
			. ', sc-comment: ' . $commentReply['comment_id']
			. ', member: ' . $commentReply['comment_user_id']
			. '"]'
			. $commentReply['comment_message']
			. "[/QUOTE]\n";
	}
	
	public function upgrade3000011Step10()
	{
		$sm = $this->schemaManager();
	
		$sm->dropTable('xf_xa_sc_comment_reply');
	
		$sm->alterTable('xf_xa_sc_comment', function(Alter $table)
		{
			$table->dropColumns(['reply_count', 'first_reply_date', 'last_reply_date', 'latest_reply_ids']);
		});
	}
	
	public function upgrade3000011Step11(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
	
		$perPage = 250;
		$db = $this->db();
	
		$itemIds = $db->fetchAllColumn($db->limit(
			'
				SELECT item_id
				FROM xf_xa_sc_item
				WHERE item_id > ?
				ORDER BY item_id
			', $perPage
		), $stepParams['position']);
		if (!$itemIds)
		{
			return true;
		}
	
		$db->beginTransaction();
	
		foreach ($itemIds AS $itemId)
		{
			$count = $db->fetchOne('
				SELECT  COUNT(*)
				FROM xf_xa_sc_comment
				WHERE item_id = ?
				AND comment_state = \'visible\'
			', $itemId);
	
			$db->update('xf_xa_sc_item', ['comment_count' => intval($count)], 'item_id = ?', $itemId);
		}
	
		$db->commit();
	
		$stepParams['position'] = end($itemIds);
	
		return $stepParams;
	}	
	
	public function upgrade3000011Step12()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
	
		$sm->alterTable('xf_xa_sc_item_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			
			$table->addColumn('match_params', 'blob')->after('match_type');
		});
	
		foreach ($db->fetchAllKeyed("SELECT * FROM xf_xa_sc_item_field", 'field_id') AS $fieldId => $field)
		{
			if (!isset($field['match_regex']))
			{
				continue;
			}
	
			$update = [];
			$matchParams = [];
	
			switch ($field['match_type'])
			{
				case 'regex':
					if ($field['match_regex'])
					{
						$matchParams['regex'] = $field['match_regex'];
					}
					break;
	
				case 'callback':
					if ($field['match_callback_class'] && $field['match_callback_method'])
					{
						$matchParams['callback_class'] = $field['match_callback_class'];
						$matchParams['callback_method'] = $field['match_callback_method'];
					}
					break;
			}
	
			if (!empty($matchParams))
			{
				$update['match_params'] = json_encode($matchParams);
			}
	
			if ($field['field_choices'] && $fieldChoices = @unserialize($field['field_choices']))
			{
				$update['field_choices'] = json_encode($fieldChoices);
			}
	
			if (!empty($update))
			{
				$db->update('xf_xa_sc_item_field', $update, 'field_id = ?', $fieldId);
			}
		} 
	
		$sm->alterTable('xf_xa_sc_item_field', function(Alter $table)
		{

			$table->addColumn('hide_title', 'tinyint')->after('field_id')->setDefault(0)->after('display_template');
			$table->addColumn('display_on_tab', 'tinyint')->setDefault(0)->after('display_on_list');
			$table->addColumn('display_on_tab_field_id', 'varchar', 25)->setDefault('')->after('display_on_tab');
			$table->addColumn('allow_use_field_user_group_ids', 'blob');
			$table->addColumn('allow_view_field_owner_in_user_group_ids', 'blob');
			
			$table->dropColumns(['match_regex', 'match_callback_class', 'match_callback_method']);
		});
	
		$sm->alterTable('xf_xa_sc_item_field_value', function(Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
		});
	
		$sm->renameTable('xf_xa_sc_item_field_category', 'xf_xa_sc_category_field');
	
		$sm->alterTable('xf_xa_sc_category_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('category_id')->length(10)->unsigned();
		});
	
		$this->query("UPDATE xf_xa_sc_item_field SET field_type = 'stars' WHERE field_type = 'rating'");
		$this->query("UPDATE xf_xa_sc_item_field SET field_type = 'textbox' WHERE field_type = 'datepicker'");
		
		// update locations from tabx to section_x
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_1' WHERE display_group = 'tab1'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_2' WHERE display_group = 'tab2'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_3' WHERE display_group = 'tab3'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_4' WHERE display_group = 'tab4'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_5' WHERE display_group = 'tab5'");
	}	
	
	public function upgrade3000011Step13()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
	
		$sm->alterTable('xf_xa_sc_review_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
		});
	
		foreach ($db->fetchAllKeyed("SELECT * FROM xf_xa_sc_review_field", 'field_id') AS $fieldId => $field)
		{
			if (!isset($field['match_regex']))
			{
				// column removed already, this has been run
				continue;
			}
	
			$update = [];
			$matchParams = [];
	
			switch ($field['match_type'])
			{
				case 'regex':
					if ($field['match_regex'])
					{
						$matchParams['regex'] = $field['match_regex'];
					}
					break;
	
				case 'callback':
					if ($field['match_callback_class'] && $field['match_callback_method'])
					{
						$matchParams['callback_class'] = $field['match_callback_class'];
						$matchParams['callback_method'] = $field['match_callback_method'];
					}
					break;
			}
	
			if (!empty($matchParams))
			{
				$update['match_params'] = json_encode($matchParams);
			}
	
			if ($field['field_choices'] && $fieldChoices = @unserialize($field['field_choices']))
			{
				$update['field_choices'] = json_encode($fieldChoices);
			}
	
			if (!empty($update))
			{
				$db->update('xf_xa_sc_review_field', $update, 'field_id = ?', $fieldId);
			}
		}
	
		$sm->alterTable('xf_xa_sc_review_field', function(Alter $table)
		{
			$table->dropColumns(['display_in_block', 'match_regex', 'match_callback_class', 'match_callback_method']);
		});
	
		$sm->alterTable('xf_xa_sc_review_field_value', function(Alter $table)
		{
			$table->renameColumn('rate_review_id', 'rating_id');
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
	
			$table->dropColumns('item_id');
			$table->dropIndexes('itemId_fieldId');
		});
	
		$sm->renameTable('xf_xa_sc_review_field_category', 'xf_xa_sc_category_review_field');
	
		$sm->alterTable('xf_xa_sc_category_review_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('category_id')->length(10)->unsigned();
		});
	
		$this->query("
			UPDATE xf_xa_sc_review_field
			SET field_type = 'stars'
			WHERE field_type = 'rating'
		");
	
		$this->query("
			UPDATE xf_xa_sc_review_field
			SET field_type = 'textbox'
			WHERE field_type = 'datepicker'
		");
	}
	
	public function upgrade3000011Step14()
	{
		$map = [
			'showcase_prefix_group_*' => 'sc_item_prefix_group.*',
			'showcase_prefix_*' => 'sc_item_prefix.*',
			'sc_custom_field_*_choice_*' => 'xa_sc_item_field_choice.$1_$2',
			'sc_custom_field_*_desc' => 'xa_sc_item_field_desc.*',
			'sc_custom_field_*' => 'xa_sc_item_field_title.*',
			'sc_review_field_*_choice_*' => 'xa_sc_review_field_choice.$1_$2',
			'sc_review_field_*_desc' => 'xa_sc_review_field_desc.*',
			'sc_review_field_*' => 'xa_sc_review_field_title.*',
		];
	
		$db = $this->db();
	
		foreach ($map AS $from => $to)
		{
			$mySqlRegex = '^' . str_replace('*', '[a-zA-Z0-9_]+', $from) . '$';
			$phpRegex = '/^' . str_replace('*', '([a-zA-Z0-9_]+)', $from) . '$/';
			$replace = str_replace('*', '$1', $to);
	
			$results = $db->fetchPairs("
				SELECT phrase_id, title
				FROM xf_phrase
				WHERE title RLIKE BINARY ?
					AND addon_id = ''
			", $mySqlRegex);
			if ($results)
			{
				/** @var \XF\Entity\Phrase[] $phrases */
				$phrases = \XF::em()->findByIds('XF:Phrase', array_keys($results));
				foreach ($results AS $phraseId => $oldTitle)
				{
					if (isset($phrases[$phraseId]))
					{
						$newTitle = preg_replace($phpRegex, $replace, $oldTitle);
	
						$phrase = $phrases[$phraseId];
						$phrase->title = $newTitle;
						$phrase->global_cache = false;
						$phrase->save(false);
					}
				}
			}
		}
	}	
	
	public function upgrade3000011Step15()
	{
		$db = $this->db();
	
		// update prefix CSS classes to the new name
		$prefixes = $db->fetchPairs("
			SELECT prefix_id, css_class
			FROM xf_xa_sc_item_prefix
			WHERE css_class <> ''
		");
	
		$db->beginTransaction();
	
		foreach ($prefixes AS $id => $class)
		{
			$newClass = preg_replace_callback('#prefix\s+prefix([A-Z][a-zA-Z0-9_-]*)#', function ($match)
			{
				$variant = strtolower($match[1][0]) . substr($match[1], 1);
				if ($variant == 'secondary')
				{
					$variant = 'accent';
				}
				return 'label label--' . $variant;
			}, $class);
			if ($newClass != $class)
			{
				$db->update('xf_xa_sc_item_prefix',
					['css_class' => $newClass],
					'prefix_id = ?', $id
				);
			}
		}
	
		$db->commit();
	
		// update field category cache format
		$fieldCache = [];
	
		$entries = $db->fetchAll("
			SELECT *
			FROM xf_xa_sc_category_field
		");
		foreach ($entries AS $entry)
		{
			$fieldCache[$entry['category_id']][$entry['field_id']] = $entry['field_id'];
		}
	
		$db->beginTransaction();
	
		foreach ($fieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_xa_sc_category',
				['field_cache' => serialize($cache)],
				'category_id = ?',
				$categoryId
			);
		}
	
		$db->commit();
	
		$reviewfieldCache = [];
	
		$entries = $db->fetchAll("
			SELECT *
			FROM xf_xa_sc_category_review_field
		");
		foreach ($entries AS $entry)
		{
			$reviewfieldCache[$entry['category_id']][$entry['field_id']] = $entry['field_id'];
		}
	
		$db->beginTransaction();
	
		foreach ($reviewfieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_xa_sc_category',
				['review_field_cache' => serialize($cache)],
				'category_id = ?',
				$categoryId
			);
		}
	
		$db->commit();
	}	
	
	public function upgrade3000011Step16()
	{
		$db = $this->db();
	
		$associations = $db->fetchAll("
			SELECT cp.*
			FROM xf_xa_sc_category_prefix AS cp
			INNER JOIN xf_xa_sc_item_prefix as p ON
				(cp.prefix_id = p.prefix_id)
			ORDER BY p.materialized_order
		");
	
		$cache = [];
		foreach ($associations AS $association)
		{
			$cache[$association['category_id']][$association['prefix_id']] = $association['prefix_id'];
		}
	
		$db->beginTransaction();
	
		foreach ($cache AS $categoryId => $prefixes)
		{
			$db->update(
				'xf_xa_sc_category',
				['prefix_cache' => serialize($prefixes)],
				'category_id = ?',
				$categoryId
			);
		}
	
		$db->commit(); 	}	
	
	public function upgrade3000011Step17(array $stepParams)
	{
		
		// resolves a conflict with XF 2.1 as the table 'xf_liked_content' had been renamed to 'xf_reaction_content' !
		
		if (\XF::$versionId >= 2010000) // XF 2.1.0 or greater
		{
			$stepParams = array_replace([
				'content_type_tables' => [
					'xf_approval_queue' => true,
					'xf_attachment' => true,
					'xf_deletion_log' => true,
					'xf_ip' => true,
					'xf_reaction_content' => true,
					'xf_moderator_log' => true,
					'xf_news_feed' => true,
					'xf_report' => true,
					'xf_search_index' => true,
					'xf_tag_content' => true,
					'xf_user_alert' => true,
					'xf_warning' => true
				]
			], $stepParams);
		}
		else 
			{
			$stepParams = array_replace([
				'content_type_tables' => [
					'xf_approval_queue' => true,
					'xf_attachment' => true,
					'xf_deletion_log' => true,
					'xf_ip' => true,
					'xf_liked_content' => true,
					'xf_moderator_log' => true,
					'xf_news_feed' => true,
					'xf_report' => true,
					'xf_search_index' => true,
					'xf_tag_content' => true,
					'xf_user_alert' => true,
					'xf_warning' => true
				]
			], $stepParams);
		}
		
		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');
	
		if (!$stepParams['content_type_tables'])
		{
			return true;
		}
		
		foreach ($stepParams['content_type_tables'] AS $table => $null)
		{
			$db->update($table, ['content_type' => 'sc_category'], 'content_type = ?', 'showcase_category');
			$db->update($table, ['content_type' => 'sc_item'], 'content_type = ?', 'showcase_item');
			$db->update($table, ['content_type' => 'sc_comment'], 'content_type = ?', 'showcase_comment');
			$db->update($table, ['content_type' => 'sc_rating'], 'content_type = ?', 'showcase_review');
			$db->update($table, ['content_type' => 'sc_rating_reply'], 'content_type = ?', 'showcase_review_reply');
	
			unset ($stepParams['content_type_tables'][$table]);
	
			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}
		}
	
		return $stepParams;
	}
	
	public function upgrade3000011Step18()
	{
		$sm = $this->schemaManager();
	
		$sm->createTable('xf_xa_sc_feed', function(Create $table)
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
	
		$sm->createTable('xf_xa_sc_feed_log', function(Create $table)
		{
			$table->addColumn('feed_id', 'int');
			$table->addColumn('unique_id', 'varbinary', 250);
			$table->addColumn('hash', 'char', 32)->comment('MD5(title + content)');
			$table->addColumn('item_id', 'int');
			$table->addPrimaryKey(['feed_id', 'unique_id']);
		});
	}
	
	public function upgrade3000011Step19()
	{
		$db = $this->db();
	
		$this->query("
			UPDATE xf_xa_sc_item
			SET last_update = create_date
			WHERE last_update = 0
		");
	
		$this->query("
			UPDATE xf_xa_sc_item
			SET edit_date = last_update
			WHERE edit_date = 0
		");
	}
	
	public function upgrade3000011Step20()
	{
		$this->insertNamedWidget('xa_sc_latest_reviews');
		$this->insertNamedWidget('xa_sc_latest_comments');
		$this->insertNamedWidget('xa_sc_showcase_statistics');
	}
	
	public function upgrade3000011Step21()
	{
		$db = $this->db();
	
		// update the associated discussion threads with the content type
		$this->query("UPDATE xf_thread SET discussion_type = 'sc_item' WHERE discussion_type = 'showcase'");
	}
	
	
	// ################################ UPGRADE TO 3.0.0 Beta 3 ##################
	
	public function upgrade3000033Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('content_term', 'varchar', 100)->setDefault('')->after('content_title');
		});
	}
	
	
	// ################################ UPGRADE TO 3.0.0 Beta 4 ##################
	
	public function upgrade3000034Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->addColumn('last_edit_date', 'int')->setDefault(0)->after('attach_count');
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0)->after('last_edit_date');
			$table->addColumn('edit_count', 'int')->setDefault(0)->after('last_edit_user_id');
		});
	}
	

	// ################################ UPGRADE TO 3.0.1 ##################

	public function upgrade3000170Step1()	
	{
		// fixes an issue where last_edit_date was being set when content was created
		$this->query("UPDATE xf_xa_sc_item SET last_edit_date = 0 WHERE edit_count = 0");
		$this->query("UPDATE xf_xa_sc_item_rating SET last_edit_date = 0 WHERE edit_count = 0");
	}

	
	// ################################ UPGRADE TO 3.0.2 ##################
	
	public function upgrade3000270Step1()
	{
		$sm = $this->schemaManager();
		
		// Some TITLE lengths that should be 150 may be incorrectly set to 100, so lets force a change of 150 on all of them to make sure they are all set to the correct length of 150.
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
		});
		
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->changeColumn('last_item_title')->length(150);
		});
		
		// drop the xfmg fields as we no longer use these
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{		
			$table->dropColumns(['xfmg_album_id', 'xfmg_media_ids', 'xfmg_video_ids']);
		});
	}	
	
	
	// ################################ UPGRADE TO 3.1.0 Beta 1 ##################
	
	public function upgrade3010031Step1(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson(
			'XenAddons\Showcase:Item', ['like_users', 'custom_fields', 'tags', 'location_data'], $position, $stepData
		);
	}
	
	public function upgrade3010031Step2(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson(
			'XenAddons\Showcase:ItemRating', ['like_users', 'custom_fields', 'latest_reply_ids'], $position, $stepData);
	}
	
	public function upgrade3010031Step3(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson(
			'XenAddons\Showcase:ItemRatingReply', ['like_users'], $position, $stepData);
	}
	
	public function upgrade3010031Step4(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson(
			'XenAddons\Showcase:Category', ['field_cache', 'review_field_cache', 'prefix_cache', 'breadcrumb_data'], $position, $stepData
		);
	}
	
	public function upgrade3010031Step5(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson('XenAddons\Showcase:Comment', ['like_users'], $position, $stepData);
	}
	
	public function upgrade3010031Step6()
	{
		$this->migrateTableToReactions('xf_xa_sc_item');
	}
	
	public function upgrade3010031Step7()
	{
		$this->migrateTableToReactions('xf_xa_sc_item_rating');
	}
	
	public function upgrade3010031Step8()
	{
		$this->migrateTableToReactions('xf_xa_sc_item_rating_reply');
	}
	
	public function upgrade3010031Step9()
	{
		$this->migrateTableToReactions('xf_xa_sc_comment');
	}
	
	public function upgrade3010031Step10()
	{
		$this->renameLikeAlertOptionsToReactions(['sc_item', 'sc_comment', 'sc_rating', 'sc_rating_reply']);
	}
	
	public function upgrade3010031Step11()
	{
		$this->renameLikeAlertsToReactions(['sc_item', 'sc_comment', 'sc_rating', 'sc_rating_reply']);
	}
	
	public function upgrade3010031Step12()
	{
		$this->renameLikePermissionsToReactions([
			'xa_showcase' => true // global and content
		], 'like');
	
		$this->renameLikePermissionsToReactions([
			'xa_showcase' => true // global and content
		], 'likeReview', 'reactReview');
	
		$this->renameLikePermissionsToReactions([
			'xa_showcase' => true // global and content
		], 'likeComment', 'reactComment');
	
		$this->renameLikeStatsToReactions(['item']);
	}
	
	public function upgrade3010031Step13()
	{	
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_sc_item_rating_reply', function(Alter $table)
		{
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});
	}
	
	
	// ################################ UPGRADE TO 3.1.3 ##################
	
	public function upgrade3010370Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('has_poll', 'tinyint')->setDefault(0)->after('tags');
		});
		
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('allow_poll', 'tinyint')->setDefault(0)->after('min_tags');
		});
	}
	
	
	// ################################ UPGRADE TO 3.1.4 ##################
	
	public function upgrade3010470Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('item_list_order', 'varchar', 25)->setDefault('');
		});
	}
	
	
	// ################################ UPGRADE TO 3.1.6 ##################
	
	public function upgrade3010670Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('map_options', 'mediumblob');
		});
	}
	
	
	// ################################ UPGRADE TO 3.1.7 ##################
	
	public function upgrade3010770Step1()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('editor_s1', 'tinyint')->setDefault(1)->after('description_s5');
		});

		$sm->alterTable('xf_xa_sc_item_field', function(Alter $table)
		{
			$table->addColumn('editable_user_group_ids', 'blob');
		});
		
		$db = $this->db();
		$db->beginTransaction();
		
		$fields = $db->fetchAll("
			SELECT *
			FROM xf_xa_sc_item_field
		");
		foreach ($fields AS $field)
		{
			$update = '-1';
		
			$db->update('xf_xa_sc_item_field',
				['editable_user_group_ids' => $update],
				'field_id = ?',
				$field['field_id']
			);
		}
		
		$db->commit();
		
		// drop all of these Showcase 2.x fields that are no longer being used (They were used for a bespoke custom field searching and filtering system)
		$sm->alterTable('xf_xa_sc_item_field', function(Alter $table)
		{
			$table->dropColumns(['is_searchable', 'is_filter_link', 'fs_description', 'allow_use_field_user_group_ids', 'allow_view_field_user_group_ids', 'allow_view_field_owner_in_user_group_ids']);
		});
	}
		
	public function upgrade3010770Step2()
	{		
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_sc_review_field', function(Alter $table)
		{
			$table->addColumn('editable_user_group_ids', 'blob');
		});
		
		$db = $this->db();
		$db->beginTransaction();
		
		$fields = $db->fetchAll("
			SELECT *
			FROM xf_xa_sc_review_field
		");
		foreach ($fields AS $field)
		{
			$update = '-1';
		
			$db->update('xf_xa_sc_review_field',
				['editable_user_group_ids' => $update],
				'field_id = ?',
				$field['field_id']
			);
		}
		
		$db->commit();
		
		// drop all of these Showcase 2.x fields that are no longer being used (They were used for a bespoke custom field searching and filtering system)
		$sm->alterTable('xf_xa_sc_review_field', function(Alter $table)
		{
			$table->dropColumns(['allow_view_field_user_group_ids']);
		});
	}

	
	// ################################ UPGRADE TO 3.1.8 ##################
	
	public function upgrade3010870Step1()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_sc_category', function(Alter $table)
		{		
			$table->addColumn('default_tags', 'mediumblob')->after('min_tags');
		});
	}	
	
	
	// ################################ UPGRADE TO 3.1.11 ##################
	
	public function upgrade3011170Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('cover_image_above_item', 'tinyint')->setDefault(1)->after('cover_image_id');
			
			$table->changeColumn('item_state', 'enum')->values(['visible','moderated','deleted','awaiting','draft'])->setDefault('visible');
		});
	}
	
	
	// ################################ UPGRADE TO 3.1.12 ##################
	
	public function upgrade3011270Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('last_feature_date', 'int')->setDefault(0)->after('last_update');
		});
	}
	
	
	// ################################ UPGRADE TO 3.1.13 ##################
	
	public function upgrade3011370Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('ratings_open', 'tinyint')->setDefault(1)->after('comments_open');
		});
		
		$sm->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->addColumn('title', 'varchar', 100)->setDefault('')->after('rating');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.0 B1 ##################
	
	public function upgrade3020031Step1()
	{
		$this->addPrefixDescHelpPhrases([
			'sc_item' => 'xf_xa_sc_item_prefix'
		]);
	
		$this->insertThreadType('sc_item', 'XenAddons\Showcase:Item', 'XenAddons/Showcase');
	}
	
	public function upgrade3020031Step2()
	{
		$this->createTable('xf_xa_sc_item_contributor', function(Create $table)
		{
			$table->addColumn('item_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('is_co_owner', 'tinyint')->setDefault(0);
			$table->addPrimaryKey(['item_id', 'user_id']);
			$table->addKey('user_id');
		});
	}
	
	public function upgrade3020031Step3()
	{
		$this->alterTable('xf_xa_sc_item_field', function(Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});
	
		$this->alterTable('xf_xa_sc_review_field', function(Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});
	}
	
	public function upgrade3020031Step4()
	{
		$this->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->addColumn('vote_score', 'int')->unsigned(false);
			$table->addColumn('vote_count', 'int')->setDefault(0);
			$table->addColumn('author_response_contributor_user_id', 'int')->setDefault(0)->after('message');
			$table->addColumn('author_response_contributor_username', 'varchar', 50)->setDefault('')->after('author_response_contributor_user_id');
			$table->addKey('author_response_contributor_user_id');
		});
	}
	
	public function upgrade3020031Step5()
	{
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('contributor_user_ids', 'blob')->after('username');
		});
	}
	
	public function upgrade3020031Step6()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('review_voting', 'varchar', 25)->setDefault('')->after('allow_ratings');
			$table->addColumn('allow_contributors', 'tinyint')->setDefault(0)->after('allow_items');
		});
	}	
	
	
	// ################################ UPGRADE TO 3.2.0 B3 ##################
	
	public function upgrade3020033Step1()
	{
		$this->addPrefixDescHelpPhrases([
			'sc_item' => 'xf_xa_sc_item_prefix'
		]);
	}
	
	
	// ################################ UPGRADE TO 3.2.1 ##################
	
	public function upgrade3020170Step1()
	{
		// check to see if item rating table has the field 'title_legacy' and remove it.
		$this->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->dropColumns(['title_legacy']);
		});
	}
	
	// ################################ UPGRADE TO 3.2.3 ##################
	
	public function upgrade3020370Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('thread_set_item_tags', 'tinyint')->setDefault(0)->after('thread_prefix_id');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.4 ##################
	
	public function upgrade3020470Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('expand_category_nav', 'tinyint')->setDefault(0);
		});
	}	
	
	
	// ################################ UPGRADE TO 3.2.7 ##################
	
	public function upgrade3020770Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('title_s6', 'varchar', 50)->after('title_s5');
			$table->addColumn('description_s6', 'text')->after('description_s5');
			$table->addColumn('editor_s6', 'tinyint')->setDefault(0)->after('editor_s5');			
			$table->addColumn('display_location_on_list', 'tinyint')->setDefault(0);
			$table->addColumn('location_on_list_display_type', 'varchar', 50);
		});
	}
	
	public function upgrade3020770Step2()
	{
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('message_s6', 'mediumtext')->after('message_s5');
			$table->addColumn('watch_count', 'int')->setDefault(0)->after('view_count');
		});
	
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->dropColumns(['cover_image_above_item']);
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.10 ##################
		
	public function upgrade3021070Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{			
			$table->addColumn('min_message_length_s1', 'int')->setDefault(0)->after('editor_s6');
			$table->addColumn('min_message_length_s2', 'int')->setDefault(0)->after('min_message_length_s1');
			$table->addColumn('min_message_length_s3', 'int')->setDefault(0)->after('min_message_length_s2');
			$table->addColumn('min_message_length_s4', 'int')->setDefault(0)->after('min_message_length_s3');
			$table->addColumn('min_message_length_s5', 'int')->setDefault(0)->after('min_message_length_s4');
			$table->addColumn('min_message_length_s6', 'int')->setDefault(0)->after('min_message_length_s5');
			$table->addColumn('allow_business_hours', 'tinyint')->setDefault(0)->after('allow_location');
		});
	}
	
	public function upgrade3021070Step2()
	{
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('sticky', 'tinyint')->setDefault(0)->after('item_state');			
			$table->addColumn('business_hours', 'mediumblob')->after('location_data');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.11 ##################
	
	public function upgrade3021170Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('display_items_on_index', 'tinyint')->setDefault(1)->after('map_options');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.12 ##################
	
	public function upgrade3021270Step1()
	{
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_sc_comment_count', 'int')->setDefault(0)->after('xa_sc_item_count');
			$table->addKey('xa_sc_comment_count', 'sc_comment_count');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.15 ##################	

	public function upgrade3021570Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('update_field_cache', 'mediumblob')->after('review_field_cache');
		});
		
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('update_count', 'int')->setDefault(0)->after('watch_count');
		});
	}
	
	public function upgrade3021570Step2()
	{
		$this->createTable('xf_xa_sc_item_update', function(Create $table)
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
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey(['item_id', 'update_date']);
		});
	}

	public function upgrade3021570Step3()
	{
		$this->createTable('xf_xa_sc_category_update_field', function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('category_id', 'int');
			$table->addPrimaryKey(['field_id', 'category_id']);
			$table->addKey('category_id');
		});
		
		$this->createTable('xf_xa_sc_update_field', function(Create $table)
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

		$this->createTable('xf_xa_sc_update_field_value', function(Create $table)
		{
			$table->addColumn('item_update_id', 'int');
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('field_value', 'mediumtext');
			$table->addPrimaryKey(['item_update_id', 'field_id']);
			$table->addKey('field_id');
		});
	}		
	
	
	// ################################ UPGRADE TO 3.2.16 ##################
	
	// Replies enhancement to Item Updates.... 
	
	public function upgrade3021670Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('autopost_review', 'tinyint')->setDefault(0)->after('thread_set_item_tags');
			$table->addColumn('autopost_update', 'tinyint')->setDefault(0)->after('autopost_review');
		});
		
		// UPDATE EXISTING CATEGORY RECORDS, Check to see if there is a thread_node_id set and if so, enabled the autoposts!
		$this->query("UPDATE xf_xa_sc_category SET autopost_review = 1 WHERE thread_node_id > 0");
		$this->query("UPDATE xf_xa_sc_category SET autopost_update = 1 WHERE thread_node_id > 0");
	
		$this->alterTable('xf_xa_sc_item_update', function(Alter $table)
		{
			$table->addColumn('reply_count', 'int')->setDefault(0)->after('edit_count');
			$table->addColumn('first_reply_date', 'int')->setDefault(0)->after('reply_count');
			$table->addColumn('last_reply_date', 'int')->setDefault(0)->after('first_reply_date');
			$table->addColumn('latest_reply_ids', 'blob')->after('last_reply_date');
		});
	}
	
	public function upgrade3021670Step2()
	{
		$this->createTable('xf_xa_sc_item_update_reply', function(Create $table)
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
	
	
	// ################################ UPGRADE TO 3.2.17 ##################
	
	
	public function upgrade3021770Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('description');
			$table->addColumn('allow_index', 'enum')->values(['allow', 'deny', 'criteria'])->setDefault('allow');
			$table->addColumn('index_criteria', 'blob');
		});
	}
	
	public function upgrade3021770Step2()
	{
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.18 ##################
	
	
	public function upgrade3021870Step1()
	{
		// lets add this again for those that have versions that are missing this column! 
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('description');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.19 ##################
	
	public function upgrade3021970Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->changeColumn('content_image', 'varchar', 200);
		});
	
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->renameColumn('content_image', 'content_image_url');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.21 ##################
	
	public function upgrade3022170Step1()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('allow_self_join_contributors', 'tinyint')->setDefault(0)->after('allow_contributors');
			$table->addColumn('max_allowed_contributors', 'smallint')->setDefault(0)->after('allow_self_join_contributors');
		});
	
		// Set a default for max_allowed_contributors if allow_contrinutors is set
		$this->query("UPDATE xf_xa_sc_category SET max_allowed_contributors = 25 WHERE allow_contributors = 1");
	}
	
	
	// ################################ UPGRADE TO 3.2.24 ##################
	
	public function upgrade3022470Step1()
	{	
		// The author response to a review in Showcase was replaced with replies to reviews several version ago
		// so lets remove fields and indexes no longer being used... 
		$this->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->dropColumns(['author_response_contributor_user_id', 'author_response_contributor_username', 'author_response']);
		});
		
		$this->alterTable('xf_xa_sc_item_rating', function(Alter $table)
		{
			$table->dropIndexes(['author_response_contributor_user_id']);
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.26 ##################
	
	public function upgrade3022670Step1()
	{
		$this->createTable('xf_xa_sc_item_page', function(Create $table)
		{
			$table->addColumn('page_id', 'int')->autoIncrement();
			$table->addColumn('item_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('page_state', 'enum')->values(['visible','deleted', 'draft'])->setDefault('visible');
			$table->addColumn('create_date', 'int')->setDefault(0);
			$table->addColumn('edit_date', 'int')->setDefault(0);
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('title', 'varchar', 150)->setDefault('');
			$table->addColumn('og_title', 'varchar', 100)->setDefault('');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('');
			$table->addColumn('display_byline', 'tinyint')->setDefault(0);
			$table->addColumn('depth', 'int')->setDefault(0);
			$table->addColumn('description', 'varchar', 256)->setDefault('');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('');
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('cover_image_id', 'int')->setDefault(0);
			$table->addColumn('cover_image_above_page', 'tinyint')->setDefault(0);
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
			$table->addKey('user_id');
			$table->addKey(['item_id', 'create_date']);
			$table->addKey(['item_id', 'display_order']);
			$table->addKey('create_date');
		});
	}
	
	public function upgrade3022670Step2()
	{		
		$this->createTable('xf_xa_sc_series', function(Create $table)
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
			$table->addColumn('view_count', 'int')->setDefault(0);
			$table->addColumn('watch_count', 'int')->setDefault(0);
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
		
		$this->createTable('xf_xa_sc_series_feature', function(Create $table)
		{
			$table->addColumn('series_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('series_id');
			$table->addKey('feature_date');
		});
		
		$this->createTable('xf_xa_sc_series_part', function(Create $table)
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

		$this->createTable('xf_xa_sc_series_view', function(Create $table)
		{
			$table->addColumn('series_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('series_id');
		});
				
		$this->createTable('xf_xa_sc_series_watch', function(Create $table)
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
		
	public function upgrade3022670Step3()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->changeColumn('title_s1', 'varchar', 100)->setDefault(0);
			$table->changeColumn('title_s2', 'varchar', 100)->setDefault(0);
			$table->changeColumn('title_s3', 'varchar', 100)->setDefault(0);
			$table->changeColumn('title_s4', 'varchar', 100)->setDefault(0);
			$table->changeColumn('title_s5', 'varchar', 100)->setDefault(0);
			$table->changeColumn('title_s6', 'varchar', 100)->setDefault(0);
		});
	}
	
	public function upgrade3022670Step4()
	{
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('page_count', 'int')->setDefault(0)->after('update_count');
			$table->addColumn('series_part_id', 'int')->setDefault(0)->after('business_hours');
		});
	}
	
	public function upgrade3022670Step5()
	{
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_1_above' WHERE display_group = 'section_1'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_2_above' WHERE display_group = 'section_2'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_3_above' WHERE display_group = 'section_3'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_4_above' WHERE display_group = 'section_4'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_5_above' WHERE display_group = 'section_5'");
		$this->query("UPDATE xf_xa_sc_item_field SET display_group = 'section_6_above' WHERE display_group = 'section_6'");
	}
	
	public function upgrade3022670Step6()
	{
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_sc_review_count', 'int')->setDefault(0)->after('xa_sc_comment_count');
			$table->addColumn('xa_sc_series_count', 'int')->setDefault(0)->after('xa_sc_review_count');
			$table->addKey('xa_sc_review_count', 'sc_review_count');
			$table->addKey('xa_sc_series_count', 'sc_series_count');
		});
	}	
	

	// ################################ UPGRADE TO 3.2.28 ##################
	
	public function upgrade3022870Step1()
	{
		$this->alterTable('xf_xa_sc_category', function (Alter $table)
		{
			$table->addColumn('require_location', 'tinyint')->setDefault(0)->after('allow_location');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.29 #################
	
	public function upgrade3022970Step1()
	{
		$this->alterTable('xf_xa_sc_item_page', function (Alter $table)
		{
			$table->addColumn('cover_image_caption', 'varchar', 500)->setDefault('')->after('cover_image_id');
		});
	}
	
	
	// ################################ UPGRADE TO 3.2.33 #################
	
	public function upgrade3023370Step1()
	{
		$this->alterTable('xf_xa_sc_item', function (Alter $table)
		{
			$table->addColumn('item_open', 'tinyint')->setDefault(1)->after('has_poll');
			$table->addColumn('update_replies_open', 'tinyint')->setDefault(1)->after('item_open');
		});
	}
	
	public function upgrade3023370Step2()
	{
		$widgetOptions = [
			'limit' => 10,
			'style' => 'list-view',
			'require_cover_or_content_image' => true
		];
		
		$this->insertNamedWidget('xa_sc_whats_new_overview_lastest_items', $widgetOptions);
	}

	
	// ################################ UPGRADE TO 3.3.0 Alpha 1 ##################
	
	public function upgrade3030011Step1()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaScAllowedFileExtensions\'
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
				WHERE option_id = \'xaScAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade3030011Step2()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaScCommentAllowedFileExtensions\'
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
				WHERE option_id = \'xaScCommentAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade3030011Step3()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaScReviewAllowedFileExtensions\'
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
				WHERE option_id = \'xaScReviewAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade3030011Step4(): void
	{
		$tables = [
			'xf_xa_sc_item_view',
			'xf_xa_sc_series_view',
		];
	
		foreach ($tables AS $tableName)
		{
			$this->alterTable($tableName, function (Alter $table)
			{
				$table->engine('InnoDB');
			});
		}
	}
	
	public function upgrade3030011Step5(): void
	{
		$this->alterTable('xf_xa_sc_series', function (Alter $table)
		{
			$table->addColumn('icon_optimized', 'tinyint')->setDefault(0)->after('icon_date');
		});
	}	
	
	
	// ################################ UPGRADE TO 3.3.0 Release Candidate 5 ##################
	
	public function upgrade3030055Step1()
	{
		$this->insertNamedWidget('xa_sc_trending_items');
	}
	
	
	// ################################ UPGRADE TO 3.3.4 ##################
	
	public function upgrade3030470Step1()
	{
		$this->alterTable('xf_xa_sc_item', function(Alter $table)
		{
			$table->addColumn('disable_self_join_contributors', 'tinyint')->setDefault(0)->after('ratings_open');
		});
	}
	
	
	// ################################ UPGRADE TO 3.3.5 ##################
	
	public function upgrade3030570Step1()
	{
		$this->alterTable('xf_xa_sc_item_rating_reply', function(Alter $table)
		{
			$table->addColumn('last_edit_date', 'int')->setDefault(0)->after('warning_message');
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0)->after('last_edit_date');
			$table->addColumn('edit_count', 'int')->setDefault(0)->after('last_edit_user_id');			
		});
	}

	public function upgrade3030570Step2()
	{
		$this->alterTable('xf_xa_sc_item_update_reply', function(Alter $table)
		{
			$table->addColumn('last_edit_date', 'int')->setDefault(0)->after('warning_message');
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0)->after('last_edit_date');
			$table->addColumn('edit_count', 'int')->setDefault(0)->after('last_edit_user_id');				
		});
	}
	
	public function upgrade3030570Step3()
	{
		$this->alterTable('xf_xa_sc_category', function(Alter $table)
		{
			$table->addColumn('display_who_viewed_this_item', 'tinyint')->setDefault(1)->after('location_on_list_display_type');
		});
	}

	
	
	// ################################ UPGRADE TO 3.3.6 ##################
	
	public function upgrade3030670Step1()
	{
		$this->alterTable('xf_xa_sc_category', function (Alter $table)
		{
			$table->addColumn('auto_feature', 'tinyint')->setDefault(0)->after('display_who_viewed_this_item');
		});
	}
	
	public function upgrade3030670Step2()
	{
		$this->alterTable('xf_xa_sc_item', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade3030670Step3()
	{
		$this->alterTable('xf_xa_sc_series', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade3030670Step4(): void
	{
		$db = $this->db();
	
		$featuredItems = $db->fetchAllKeyed(
				'SELECT item.*, item_feature.*
			FROM xf_xa_sc_item AS item
			INNER JOIN xf_xa_sc_item_feature AS item_feature
				ON (item_feature.item_id = item.item_id)
			ORDER BY item.item_id ASC',
				'item_id'
		);
	
		$rows = [];
		foreach ($featuredItems AS $itemId => $item)
		{
			$rows[] = [
				'content_type' => 'sc_item',
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
				'xf_xa_sc_item',
				['featured' => 1],
				'item_id IN (' . $db->quote(array_keys($featuredItems)) . ')'
			);
	
			$db->commit();
		}
	}
	
	public function upgrade3030670Step5(): void
	{
		$db = $this->db();
	
		$featuredSeries = $db->fetchAllKeyed(
			'SELECT series.*, series_feature.*
			FROM xf_xa_sc_series AS series
			INNER JOIN xf_xa_sc_series_feature AS series_feature
				ON (series_feature.series_id = series.series_id)
			ORDER BY series.series_id ASC',
				'series_id'
		);
	
		$rows = [];
		foreach ($featuredSeries AS $seriesId => $series)
		{
			$rows[] = [
				'content_type' => 'sc_series',
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
				'xf_xa_sc_series',
				['featured' => 1],
				'series_id IN (' . $db->quote(array_keys($featuredSeries)) . ')'
			);
	
			$db->commit();
		}
	}
	
	public function upgrade3030670Step6(): void
	{
		$this->dropTable('xf_xa_sc_item_feature');
	
		$this->dropTable('xf_xa_sc_series_feature');
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
	
		if ($previousVersion && $previousVersion < 3000010)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_scUpgradeItemEmbedMetadataRebuild',
				'XenAddons\Showcase:ScItemEmbedMetadata',
				['types' => 'attachments'],
				false
			);
				
			$this->app->jobManager()->enqueueUnique(
				'xa_scUpgradeCommentEmbedMetadataRebuild',
				'XenAddons\Showcase:ScCommentEmbedMetadata',
				['types' => 'attachments'],
				false
			);
				
			$this->app->jobManager()->enqueueUnique(
				'xa_scUpgradeReviewEmbedMetadataRebuild',
				'XenAddons\Showcase:ScReviewEmbedMetadata',
				['types' => 'attachments'],
				false
			);
	
			/** @var \XF\Service\RebuildNestedSet $service */
			$service = \XF::service('XF:RebuildNestedSet', 'XenAddons\Showcase:Category', [
				'parentField' => 'parent_category_id'
			]);
			$service->rebuildNestedSetInfo();
				
			$likeContentTypes = [
				'sc_item',
				'sc_comment',
				'sc_rating',
				'sc_rating_reply'
			];
			foreach ($likeContentTypes AS $contentType)
			{
				$this->app->jobManager()->enqueueUnique(
					'xa_scUpgradeLikeIsCountedRebuild_' . $contentType,
					'XF:LikeIsCounted',
					['type' => $contentType],
					false
				);
			}
		}

		if ($previousVersion && $previousVersion < 3010670)
		{		
			// Update all Showcase items that have a location set with new geolocation data
			$this->app->jobManager()->enqueueUnique(
				'xa_scUpgradeItemLocationDataRebuild',
				'XenAddons\Showcase:ItemLocationData',
				[],
				false
			);
		}
		
		if ($previousVersion && $previousVersion < 3020770)
		{		
			// run job to rebuild items (to set the new watch_count cache field)
			$this->app->jobManager()->enqueueUnique(
				'scUpgradeRebuildItems',
				'XenAddons\Showcase:Item',
				[],
				false
			);
				
			// run job to rebuild item location data (to set all the new location data)
			$this->app->jobManager()->enqueueUnique(
				'scUpgradeRebuildItemLocationData',
				'XenAddons\Showcase:ItemLocationData',
				[],
				false
			);
		}
		
		if ($previousVersion && $previousVersion < 3021270)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_scUpgradeUserCountRebuild',
				'XenAddons\Showcase:UserItemCount',
				[],
				false
			);
		}

		if ($previousVersion && $previousVersion < 3022670)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_scUpgradeUserCountRebuild',
				'XenAddons\Showcase:UserItemCount',
				[],
				false
			);
		}

		// the following runs after every upgrade
		\XF::repository('XenAddons\Showcase:ItemPrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\Showcase:ItemField')->rebuildFieldCache();
		\XF::repository('XenAddons\Showcase:ReviewField')->rebuildFieldCache();
		\XF::repository('XenAddons\Showcase:UpdateField')->rebuildFieldCache();

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
			$table->dropColumns(['xa_sc_item_count', 'xa_sc_comment_count', 'xa_sc_review_count', 'xa_sc_series_count']);
		});
	}
	
	public function uninstallStep3()
	{
		$db = $this->db();
	
		$contentTypes = [
			'sc_category', 
			'sc_comment', 
			'sc_item', 
			'sc_page',
			'sc_rating', 
			'sc_rating_reply',
			'sc_series',
			'sc_series_part',
			'sc_update', 
			'sc_update_reply'
		];
		
		$this->uninstallContentTypeData($contentTypes);
	
		$db->beginTransaction();
	
		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'showcase'");
		$db->delete('xf_permission_cache_content', "content_type = 'sc_category'");
		$db->delete('xf_permission_entry', "permission_group_id = 'xa_showcase'");
		$db->delete('xf_permission_entry_content', "permission_group_id = 'xa_showcase'");
	
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
			'xa_sc_trending_items' => function ($key, array $options = [])
			{
				$options = array_replace([
					'contextual' => true,
					'style' => 'simple',
					'content_type' => 'sc_item',
				], $options);
					
				$this->createWidget(
					$key,
					'trending_content',
					[
						'positions' => [
							'xa_sc_index_sidenav' => 150,
							'xa_sc_category_sidenav' => 150,
						],
						'options' => $options,
					],
					'Trending items'
				);
			},		
			'xa_sc_latest_updates' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_sc_latest_updates',
					[
						'positions' => [
							'xa_sc_index_sidenav' => 200, 
							'xa_sc_category_sidenav' => 200
						],
						'options' => $options
					]
				);
			},		
			'xa_sc_latest_reviews' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
			
				$this->createWidget(
					$key,
					'xa_sc_latest_reviews',
					[
						'positions' => [
							'xa_sc_index_sidenav' => 300,
							'xa_sc_category_sidenav' => 300
						],
						'options' => $options
					]
				);
			},
			'xa_sc_latest_comments' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_sc_latest_comments',
					[
						'positions' => [
							'xa_sc_index_sidenav' => 400,
							'xa_sc_category_sidenav' => 400
						],
						'options' => $options
					]
				);
			},
			'xa_sc_showcase_statistics' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_sc_showcase_statistics',
					[
						'positions' => ['xa_sc_index_sidenav' => 1000],
						'options' => $options
					]
				);
			},
			'xa_sc_whats_new_overview_lastest_items' => function($key, array $options = [])
			{
				$options = array_replace([
					'limit' => 10,
					'style' => 'list-view',
					'require_cover_or_content_image' => true
				], $options);
					
				$this->createWidget(
					$key,
					'xa_sc_latest_items',
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
			$this->applyGlobalPermission('xa_showcase', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'viewFull', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'viewItemAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'react', 'forum', 'react');
			$this->applyGlobalPermission('xa_showcase', 'add', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_showcase', 'uploadItemAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_showcase', 'editOwn', 'forum', 'editOwnPost');
				
			$this->applyGlobalPermission('xa_showcase', 'viewComments', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'viewCommentAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'reactComment', 'forum', 'react');
			$this->applyGlobalPermission('xa_showcase', 'addComment', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_showcase', 'uploadCommentAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_showcase', 'editComment', 'forum', 'editOwnPost');
				
			$this->applyGlobalPermission('xa_showcase', 'viewReviews', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'viewReviewAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'reactReview', 'forum', 'react');
			$this->applyGlobalPermission('xa_showcase', 'rate', 'forum', 'react');
			$this->applyGlobalPermission('xa_showcase', 'uploadReviewAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_showcase', 'editReview', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_showcase', 'reviewReply', 'forum', 'editOwnPost');
	
			$applied = true;
		}
	
		if (!$previousVersion || $previousVersion < 2000011)
		{
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_showcase', 'inlineMod', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_showcase'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny', 'featureUnfeature')
			");
				
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_showcase', 'inlineMod', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_showcase'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny', 'featureUnfeature')
			");
				
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_showcase', 'inlineModComment', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_showcase'
					AND permission_id IN ('deleteAnyComment', 'undeleteComment', 'approveUnapproveComment', 'editAnyComment')
			");
	
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_showcase', 'inlineModComment', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_showcase'
					AND permission_id IN ('deleteAnyComment', 'undeleteComment', 'approveUnapproveComment', 'editAnyComment')
			");
				
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_showcase', 'inlineModReview', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_showcase'
					AND permission_id IN ('deleteAnyReview', 'undeleteReview', 'approveUnapproveReview', 'editAnyReview')
			");
				
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_showcase', 'inlineModReview', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_showcase'
					AND permission_id IN ('deleteAnyReview', 'undeleteReview', 'approveUnapproveReview', 'editAnyReview')
			");
	
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 3020010)
		{
			$this->applyGlobalPermission('xa_showcase', 'contentVote', 'xa_showcase', 'rate');
			$this->applyContentPermission('xa_showcase', 'contentVote', 'xa_showcase', 'rate');
			$this->applyGlobalPermission('xa_showcase', 'manageOwnContributors', 'xa_showcase', 'editOwn');
			$this->applyContentPermission('xa_showcase', 'manageOwnContributors', 'xa_showcase', 'editOwn');
			$this->applyGlobalPermission('xa_showcase', 'manageAnyContributors', 'xa_showcase', 'editAny');
			$this->applyContentPermission('xa_showcase', 'manageAnyContributors', 'xa_showcase', 'editAny');
		
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 3020770)
		{
			$this->applyGlobalPermission('xa_showcase', 'viewItemMap', 'xa_showcase', 'view');
			$this->applyContentPermission('xa_showcase', 'viewItemMap', 'xa_showcase', 'view');
			$this->applyGlobalPermission('xa_showcase', 'View multi marker maps', 'xa_showcase', 'view');
			$this->applyContentPermission('xa_showcase', 'View multi marker maps', 'xa_showcase', 'view');
				
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 3022670)
		{
			// XenAddons\Showcase: Item permissions
			$this->applyGlobalPermission('xa_showcase', 'addPageOwnItem', 'forum', 'postThread');
			
			// XenAddons\Showcase: Series permissions
			$this->applyGlobalPermission('xa_showcase', 'viewSeries', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'viewSeriesAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_showcase', 'reactSeries', 'forum', 'react');
			$this->applyGlobalPermission('xa_showcase', 'createSeries', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_showcase', 'addSeriesWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_showcase', 'uploadSeriesAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_showcase', 'uploadSeriesVideo', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_showcase', 'editOwnSeries', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_showcase', 'deleteOwnSeries', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xa_showcase', 'tagOwnSeries', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('xa_showcase', 'tagAnySeries', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xa_showcase', 'manageOthersTagsOwnSeries', 'forum', 'manageOthersTagsOwnThread');
			$this->applyGlobalPermission('xa_showcase', 'votePollSeries', 'forum', 'votePoll');
			$this->applyGlobalPermission('xa_showcase', 'addToCommunitySeries', 'forum', 'postThread');
				
			// XenAddons\Showcase: Series moderator permissions
			$this->applyGlobalPermission('xa_showcase', 'inlineModSeries', 'forum', 'inlineMod');
			$this->applyGlobalPermission('xa_showcase', 'viewModeratedSeries', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_showcase', 'viewDeletedSeries', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_showcase', 'approveUnapproveSeries', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_showcase', 'editAnySeries', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_showcase', 'deleteAnySeries', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_showcase', 'hardDeleteAnySeries', 'forum', 'hardDeleteAnyPost');			
			$this->applyGlobalPermission('xa_showcase', 'undeleteSeries', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_showcase', 'manageAnySeriesTag', 'forum', 'manageAnyTag');
			$this->applyGlobalPermission('xa_showcase', 'featureUnfeatureSeries', 'forum', 'stickUnstickThread');
			$this->applyGlobalPermission('xa_showcase', 'warnSeries', 'forum', 'warn');
			
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 3023370)
		{
			// XenAddons\Showcase: Item permissions
			$this->applyGlobalPermission('xa_showcase', 'lockUnlockUpdateReplysOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_showcase', 'copyOwn', 'forum', 'editOwnPost');
			
			// XenAddons\Showcase: Item moderator permissions
			$this->applyGlobalPermission('xa_showcase', 'copyAny', 'forum', 'editAnyPost');
			
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 3030570)
		{
			// XenAddons\Showcase: Item permissions
			$this->applyGlobalPermission('xa_showcase', 'viewWhoViewedItem', 'xa_showcase', 'viewFull');
			$this->applyGlobalPermission('xa_showcase', 'viewWhoViewedItemOwn', 'xa_showcase', 'viewFull');
				
			$applied = true;
		}
			
		return $applied;
	}	
}