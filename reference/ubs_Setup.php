<?php

namespace XenAddons\UBS;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\Widget;
use XF\Service\RebuildNestedSet;
use XenAddons\UBS\Install\Data\MySql;

use XenAddons\UBS\Repository\BlogEntryField;
use XenAddons\UBS\Repository\BlogEntryPrefix;

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
			$table->addColumn('xa_ubs_blog_count', 'int')->setDefault(0);
			$table->addColumn('xa_ubs_blog_entry_count', 'int')->setDefault(0);
			$table->addColumn('xa_ubs_series_count', 'int')->setDefault(0);
			$table->addColumn('xa_ubs_comment_count', 'int')->setDefault(0);
			$table->addColumn('xa_ubs_review_count', 'int')->setDefault(0);
			$table->addKey('xa_ubs_blog_count', 'ubs_blog_count');
			$table->addKey('xa_ubs_blog_entry_count', 'ubs_blog_entry_count');
			$table->addKey('xa_ubs_series_count', 'ubs_series_count');
			$table->addKey('xa_ubs_comment_count', 'ubs_comment_count');
			$table->addKey('xa_ubs_review_count', 'ubs_review_count');
		});
		
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->addColumn('xa_ubs_about_author', 'text')->nullable(true);
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
		
		$this->insertThreadType('ubs_blog_entry', 'XenAddons\UBS:BlogEntryItem', 'XenAddons/UBS');
	}
	
	public function installStep4()
	{
		$this->db()->query("
			REPLACE INTO `xf_route_filter`
				(`prefix`,`find_route`,`replace_route`,`enabled`,`url_to_route_only`)
			VALUES
				('ubs', 'ubs/', 'blogs/', 1, 0);
		");
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
		$service = \XF::service('XF:RebuildNestedSet', 'XenAddons\UBS:Category', [
			'parentField' => 'parent_category_id'
		]);
		$service->rebuildNestedSetInfo();
	
		\XF::repository('XenAddons\UBS:BlogEntryPrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\UBS:BlogEntryField')->rebuildFieldCache();
		\XF::repository('XenAddons\UBS:ReviewField')->rebuildFieldCache();
	}
	
	
	// ################################ UPGRADE STEPS ####################
	

	// ################################ UPGRADE TO UBS 1.0.0 RC 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1000051Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ubs_blog ADD blog_cover_image_cache BLOB NOT NULL");
		$this->query("ALTER TABLE xf_nflj_ubs_blog_entry ADD cover_image_cache BLOB NOT NULL");
	}
	

	// ################################ UPGRADE TO UBS 1.0.3  ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1000370Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ubs_blog
				ADD blog_image_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD blog_file_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD blog_entries_rating_count int(10) unsigned NOT NULL DEFAULT '0',
				ADD blog_entries_rating_avg float unsigned NOT NULL DEFAULT '0',
				ADD INDEX blog_entries_rating_avg (blog_entries_rating_avg)
		");

		$this->query("
			ALTER TABLE xf_nflj_ubs_blog_entry
				ADD image_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD file_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD blog_entry_location VARCHAR(50) NOT NULL DEFAULT ''
		");

		$this->query("
			ALTER TABLE xf_nflj_ubs_category_group
				ADD category_group_description text NOT NULL,
				ADD category_group_image varchar(50) NOT NULL,
				ADD category_group_fa_icon varchar(50) NOT NULL,
				ADD category_group_content mediumtext NOT NULL,
				ADD category_group_content_title varchar(100) NOT NULL,
				ADD category_group_style_id int(10) unsigned NOT NULL DEFAULT '0'
		");
	}

	// ################################ UPGRADE TO UBS 1.2.0 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1020070Step1()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_series (
				series_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				blog_id int(10) unsigned NOT NULL,
				series_name varchar(150) NOT NULL,
				series_description mediumtext NOT NULL,
				series_display_order int(10) unsigned NOT NULL DEFAULT '1',
				series_create_date int(10) unsigned NOT NULL DEFAULT '0',
				series_edit_date int(10) unsigned NOT NULL DEFAULT '0',
				series_part_count int(10) unsigned NOT NULL DEFAULT '0',
				series_featured tinyint(3) unsigned NOT NULL DEFAULT '0',
				last_series_part_date int(10) unsigned NOT NULL DEFAULT '0',
				last_series_part int(10) unsigned NOT NULL DEFAULT '0',
				last_series_part_title varchar(150) NOT NULL,
				last_series_part_entry_id int(10) unsigned NOT NULL DEFAULT '0',
				series_parts_rating_count int(10) unsigned NOT NULL DEFAULT '0',
				series_parts_rating_avg float unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (series_id),
				KEY series_display_order (series_display_order),
				KEY series_name (series_name),
				KEY blog_id (blog_id),
				KEY series_parts_rating_avg (series_parts_rating_avg)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_series_part (
				series_part_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				series_id int(10) unsigned NOT NULL,
				blog_id int(10) unsigned NOT NULL,
				blog_entry_id int(10) unsigned NOT NULL,
				series_part int(10) unsigned NOT NULL DEFAULT '1',
				series_part_title varchar(100) NOT NULL,
				series_part_create_date int(10) unsigned NOT NULL DEFAULT '0',
				series_part_edit_date int(10) unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (series_part_id),
				KEY series_part (series_part),
				KEY blog_id (blog_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_series_watch (
				user_id int(10) unsigned NOT NULL,
				series_id int(10) unsigned NOT NULL,
				notify_on enum('','series_part') NOT NULL,
				send_alert tinyint(3) unsigned NOT NULL,
				send_email tinyint(3) unsigned NOT NULL,
				PRIMARY KEY (user_id,series_id),
				KEY series_id_notify_on (series_id,notify_on)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");

		$this->query("ALTER TABLE xf_nflj_ubs_blog ADD blog_series_count int(10) unsigned NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_nflj_ubs_blog_entry ADD series_part_id int(10) unsigned NOT NULL DEFAULT '0' AFTER featured");
	}
		
	// ################################ UPGRADE TO 1.3.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1030031Step1()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_category_watch (
				user_id int(10) unsigned NOT NULL,
				category_id int(10) unsigned NOT NULL,
				notify_on enum('','blog_entry') NOT NULL,
				send_alert tinyint(3) unsigned NOT NULL,
				send_email tinyint(3) unsigned NOT NULL,
				PRIMARY KEY (user_id,category_id),
				KEY category_id_notify_on (category_id,notify_on)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
	}
		
	// ################################ UPGRADE TO 1.3.0 Beta 3 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1030033Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ubs_blog
				ADD INDEX blog_create_date (blog_create_date),
				ADD INDEX user_id_blog_create_date (user_id,blog_create_date)
		");

		$this->query("
			ALTER TABLE xf_nflj_ubs_blog_entry
				ADD INDEX publish_date (publish_date),
				ADD INDEX blog_id_publish_date (blog_id,publish_date),
				ADD INDEX user_id_publish_date (user_id,publish_date)
		");

		$this->query("ALTER TABLE xf_nflj_ubs_rate_review ADD INDEX rate_review_date (rate_review_date)");
	}

	// ################################ UPGRADE TO 1.4.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1040031Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ubs_blog
				ADD is_community_blog tinyint(3) unsigned NOT NULL DEFAULT '0',
				ADD is_community_blog_restricted tinyint(3) unsigned NOT NULL DEFAULT '0',
				ADD community_blog_allowed_user_ids varbinary(255) NOT NULL DEFAULT '',
				ADD community_blog_allowed_user_group_ids varbinary(255) NOT NULL DEFAULT ''
		");

		$this->query("
			ALTER TABLE xf_nflj_ubs_blog_entry
				ADD xfmg_album_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD xfmg_media_ids varbinary(100) NOT NULL DEFAULT '',
				ADD xfmg_video_ids varbinary(100) NOT NULL DEFAULT ''
		");

		$this->query("ALTER TABLE xf_nflj_ubs_blog_entry CHANGE blog_entry_location blog_entry_location varchar(255) NOT NULL DEFAULT ''");
		$this->query("ALTER TABLE xf_nflj_ubs_comment ADD attach_count int(10) unsigned NOT NULL DEFAULT '0'");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_blog_entry_reply_ban (
				blog_entry_reply_ban_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				blog_entry_id int(10) unsigned NOT NULL,
				user_id int(10) unsigned NOT NULL,
				ban_date int(10) unsigned NOT NULL,
				expiry_date int(10) unsigned DEFAULT NULL,
				reason varchar(100) NOT NULL DEFAULT '',
				ban_user_id int(10) unsigned NOT NULL,
				PRIMARY KEY (blog_entry_reply_ban_id),
				UNIQUE KEY blog_entry_id_user_id (blog_entry_id,user_id),
				KEY expiry_date (expiry_date),
				KEY user_id (user_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_blog_reply_ban (
				blog_reply_ban_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				blog_id int(10) unsigned NOT NULL,
				user_id int(10) unsigned NOT NULL,
				ban_date int(10) unsigned NOT NULL,
				expiry_date int(10) unsigned DEFAULT NULL,
				reason varchar(100) NOT NULL DEFAULT '',
				ban_user_id int(10) unsigned NOT NULL,
				PRIMARY KEY (blog_reply_ban_id),
				UNIQUE KEY blog_id_user_id (blog_id,user_id),
				KEY expiry_date (expiry_date),
				KEY user_id (user_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8
		");
	}
	
	// ################################ UPGRADE TO 1.4.0 RC 2 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1040052Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ubs_blog
				ADD warning_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD warning_message varchar(255) NOT NULL DEFAULT ''
		");

		$this->query("
			ALTER TABLE xf_nflj_ubs_blog_entry
				ADD warning_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD warning_message varchar(255) NOT NULL DEFAULT ''
		");

		$this->query("ALTER TABLE xf_nflj_ubs_rate_review ADD warning_message varchar(255) NOT NULL DEFAULT '' AFTER warning_id");
	}
		
	// ################################ UPGRADE TO 1.4.0 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1040070Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ubs_blog ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_nflj_ubs_blog_entry ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_nflj_ubs_comment ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
	}
		
	// ################################ UPGRADE TO 1.4.1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1040170Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ubs_rate_review ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
	}
		
	// ################################ UPGRADE TO 1.5.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1050031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ubs_rate_review ADD username varchar(50) NOT NULL AFTER user_id");
	}
		
	// ################################ UPGRADE TO 1.6.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1060031Step1()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_blog_entry_field (
				field_id varchar(25) NOT NULL,
				hide_title tinyint(3) unsigned NOT NULL DEFAULT '0',
				display_group varchar(25) NOT NULL DEFAULT 'sidebar',
				display_group_order int(10) unsigned NOT NULL DEFAULT '0',
				display_order int(10) unsigned NOT NULL DEFAULT '1',
				display_on_list tinyint(3) unsigned NOT NULL DEFAULT '0',
				display_on_tab tinyint(3) unsigned NOT NULL DEFAULT '0',
				display_on_tab_field_id varchar(25) NOT NULL DEFAULT '',
				field_type varchar(25) NOT NULL DEFAULT 'textbox',
				field_choices blob NOT NULL,
				rating_type varchar(25) NOT NULL DEFAULT 'full',
				match_type varchar(25) NOT NULL DEFAULT 'none',
				match_regex varchar(250) NOT NULL DEFAULT '',
				match_callback_class varchar(75) NOT NULL DEFAULT '',
				match_callback_method varchar(75) NOT NULL DEFAULT '',
				max_length int(10) unsigned NOT NULL DEFAULT '0',
				required tinyint(3) unsigned NOT NULL DEFAULT '0',
				display_template text NOT NULL,
				is_searchable tinyint(3) unsigned NOT NULL DEFAULT '0',
				is_filter_link tinyint(3) unsigned NOT NULL DEFAULT '0',
				fs_description varchar(250) NOT NULL DEFAULT '',
				allow_use_field_user_group_ids blob NOT NULL,
				allow_view_field_user_group_ids blob NOT NULL,
				allow_view_field_owner_in_user_group_ids blob NOT NULL,
				PRIMARY KEY (field_id),
				KEY display_group_order (display_group,display_order)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_blog_entry_field_value (
				blog_entry_id int(10) unsigned NOT NULL,
				field_id varchar(25) NOT NULL,
				field_value mediumtext NOT NULL,
				PRIMARY KEY (blog_entry_id,field_id),
				KEY field_id (field_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_review_field (
				field_id varchar(25) NOT NULL,
				display_group varchar(25) NOT NULL DEFAULT 'user_review',
				display_group_order int(10) unsigned NOT NULL DEFAULT '0',
				display_order int(10) unsigned NOT NULL DEFAULT '1',
				display_in_block tinyint(3) unsigned NOT NULL DEFAULT '0',
				field_type varchar(25) NOT NULL DEFAULT 'textbox',
				field_choices blob NOT NULL,
				rating_type varchar(25) NOT NULL DEFAULT 'full',
				match_type varchar(25) NOT NULL DEFAULT 'none',
				match_regex varchar(250) NOT NULL DEFAULT '',
				match_callback_class varchar(75) NOT NULL DEFAULT '',
				match_callback_method varchar(75) NOT NULL DEFAULT '',
				max_length int(10) unsigned NOT NULL DEFAULT '0',
				required tinyint(3) unsigned NOT NULL DEFAULT '0',
				display_template text NOT NULL,
				is_searchable tinyint(3) unsigned NOT NULL DEFAULT '0',
				is_filter_link tinyint(3) unsigned NOT NULL DEFAULT '0',
				fs_description varchar(250) NOT NULL DEFAULT '',
				allow_view_field_user_group_ids blob NOT NULL,
				PRIMARY KEY (field_id),
				KEY display_group_order (display_group,display_order)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ubs_review_field_value (
				rate_review_id int(10) unsigned NOT NULL,
				blog_entry_id int(10) unsigned NOT NULL,
				field_id varchar(25) NOT NULL,
				field_value mediumtext NOT NULL,
				PRIMARY KEY (rate_review_id,field_id),
				KEY field_id (field_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->query("ALTER TABLE xf_nflj_ubs_blog_entry ADD custom_blog_entry_fields mediumblob NOT NULL AFTER categories");
		$this->query("ALTER TABLE xf_nflj_ubs_rate_review ADD custom_review_fields mediumblob NOT NULL");

		$this->query("
			ALTER TABLE xf_nflj_ubs_blog
				ADD blog_entry_field_cache mediumblob NOT NULL AFTER blog_options,
				ADD review_field_cache mediumblob NOT NULL AFTER blog_entry_field_cache
		");
	}	
	
	// ################################ START OF XF2 VERSION OF UBS ##################
	
	
	// ################################ UPGRADE TO 2.0.0 Alpha 1 ##################
	
	public function upgrade2000011Step1()
	{
		$sm = $this->schemaManager();
	
		$renameTables = [
			'xf_nflj_ubs_author_watch' => 'xf_xa_ubs_author_watch',
			'xf_nflj_ubs_blog' => 'xf_xa_ubs_blog',
			'xf_nflj_ubs_blog_entry' => 'xf_xa_ubs_blog_entry',
			'xf_nflj_ubs_blog_entry_field' => 'xf_xa_ubs_blog_entry_field',
			'xf_nflj_ubs_blog_entry_field_value' => 'xf_xa_ubs_blog_entry_field_value',
			'xf_nflj_ubs_blog_entry_read' => 'xf_xa_ubs_blog_entry_read',
			'xf_nflj_ubs_blog_entry_reply_ban' => 'xf_xa_ubs_blog_entry_reply_ban',
			'xf_nflj_ubs_blog_entry_view' => 'xf_xa_ubs_blog_entry_view',
			'xf_nflj_ubs_blog_entry_watch' => 'xf_xa_ubs_blog_entry_watch',			
			'xf_nflj_ubs_blog_reply_ban' => 'xf_xa_ubs_blog_reply_ban',
			'xf_nflj_ubs_blog_watch' => 'xf_xa_ubs_blog_watch',
			'xf_nflj_ubs_category' => 'xf_xa_ubs_category',
			'xf_nflj_ubs_category_content' => 'xf_xa_ubs_category_content',
			'xf_nflj_ubs_category_group' => 'xf_xa_ubs_category_group',
			'xf_nflj_ubs_category_watch' => 'xf_xa_ubs_category_watch',
			'xf_nflj_ubs_comment' => 'xf_xa_ubs_comment',
			'xf_nflj_ubs_comment_reply' => 'xf_xa_ubs_comment_reply',
			'xf_nflj_ubs_prefix' => 'xf_xa_ubs_blog_entry_prefix',
			'xf_nflj_ubs_prefix_group' => 'xf_xa_ubs_blog_entry_prefix_group',
			'xf_nflj_ubs_rate_review' => 'xf_xa_ubs_blog_entry_rating',
			'xf_nflj_ubs_review_field' => 'xf_xa_ubs_review_field',
			'xf_nflj_ubs_review_field_value' => 'xf_xa_ubs_review_field_value',
			'xf_nflj_ubs_series' => 'xf_xa_ubs_series',
			'xf_nflj_ubs_series_part' => 'xf_xa_ubs_series_part',
			'xf_nflj_ubs_series_watch' => 'xf_xa_ubs_series_watch'
		];
		foreach ($renameTables AS $from => $to)
		{
			$sm->renameTable($from, $to);
		}
	
		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->renameColumn('ubs_blog_count', 'xa_ubs_blog_count');
			$table->renameColumn('ubs_blog_entry_count', 'xa_ubs_blog_entry_count');
			
			$table->addColumn('xa_ubs_series_count', 'int')->setDefault(0);
		});
		
		$this->schemaManager()->alterTable('xf_user_option', function(Alter $table)
		{
			$table->dropColumns('ubs_unread_blog_entries_count');
		});
		
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->addColumn('xa_ubs_about_author', 'text')->nullable(true);
		});
	
		// These are no longer needed, so lets get rid of them now!
		$sm->dropTable('xf_nflj_ubs_blog_permission');
		$sm->dropTable('xf_nflj_ubs_blog_view');
		$sm->dropTable('xf_nflj_ubs_map_category');
		$sm->dropTable('xf_nflj_ubs_map_private');
		$sm->dropTable('xf_nflj_ubs_map_shared');
	}
	
	public function upgrade2000011Step2()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->renameColumn('category_name', 'title');
			$table->renameColumn('category_description', 'description');
			$table->renameColumn('category_image', 'content_image');
			$table->renameColumn('category_content', 'content_message');
			$table->renameColumn('category_content_title', 'content_title');
			$table->renameColumn('category_style_id', 'style_id');
		});
		
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->dropColumns(['blog_entries_limit', 'materialized_order', 'category_fa_icon', 'allowed_user_group_ids', 'category_options', 'display_order_mi', 'blog_entries_limit_mi']);
		});
		
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('parent_category_id', 'int')->setDefault(0);
			$table->addColumn('legacy_category_group', 'int')->setDefault(0);
		});
	}	
	
	public function upgrade2000011Step3()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
		
		$legacyCategoryGroups = $db->fetchAll('
			SELECT *
			FROM xf_xa_ubs_category_group
			ORDER BY display_order
		');
		
		$db->beginTransaction();
		
		foreach ($legacyCategoryGroups AS $legacyCategoryGroup)
		{
			$this->db()->query("
				INSERT INTO xf_xa_ubs_category
					(category_group_id, title, description, content_image, content_message, content_title, style_id, display_order, parent_category_id, legacy_category_group)
				VALUES
					(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			", array($legacyCategoryGroup['category_group_id'], $legacyCategoryGroup['category_group_name'], $legacyCategoryGroup['category_group_description'], 
				$legacyCategoryGroup['category_group_image'], $legacyCategoryGroup['category_group_content'], $legacyCategoryGroup['category_group_content_title'], 
				$legacyCategoryGroup['category_group_style_id'], $legacyCategoryGroup['display_order'], 0, 1));
		}
		
		// Add a General Category that will be used as the Parent Category for Blog Entries that are currently not associated with any categories
		$this->db()->query("
			INSERT INTO xf_xa_ubs_category
				(category_group_id, title, description, content_image, content_message, content_title, style_id, display_order, parent_category_id, legacy_category_group)
			VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			", array(0, 'General', '',
				'', '', '',
				0, 0, 0, 2));
		
		$db->commit();
	}
	
	public function upgrade2000011Step4()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
		
		$legacyCategoryGroups = $db->fetchAll('
			SELECT *
			FROM xf_xa_ubs_category
			WHERE legacy_category_group = 1 
				OR legacy_category_group = 2
			ORDER BY display_order
		');
		
		$db->beginTransaction();
		
		foreach ($legacyCategoryGroups AS $legacyCategoryGroup)
		{
			$db->update('xf_xa_ubs_category', [
				'parent_category_id' => $legacyCategoryGroup['category_id']
			], 'category_group_id = ? AND legacy_category_group = ?', [$legacyCategoryGroup['category_group_id'], 0]);
		}
			
		$db->commit();
	}

	public function upgrade2000011Step5()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('lft', 'int')->setDefault(0);
			$table->addColumn('rgt', 'int')->setDefault(0);
			$table->addColumn('depth', 'smallint')->setDefault(0);
			$table->addColumn('blog_entry_count', 'int')->setDefault(0);
			$table->addColumn('featured_count', 'smallint')->setDefault(0);
			$table->addColumn('last_blog_entry_date', 'int')->setDefault(0);
			$table->addColumn('last_blog_entry_title', 'varchar', 150)->setDefault('');
			$table->addColumn('last_blog_entry_id', 'int')->setDefault(0);
			$table->addColumn('thread_node_id', 'int')->setDefault(0);
			$table->addColumn('thread_prefix_id', 'int')->setDefault(0);
			$table->addColumn('allow_comments', 'tinyint')->setDefault(0);
			$table->addColumn('allow_ratings', 'tinyint')->setDefault(0);
			$table->addColumn('require_review', 'tinyint')->setDefault(0);
			$table->addColumn('allow_blog_entries', 'tinyint')->setDefault(1);
			$table->addColumn('breadcrumb_data', 'blob');
			$table->addColumn('prefix_cache', 'mediumblob');
			$table->addColumn('default_prefix_id', 'int')->setDefault(0);
			$table->addColumn('require_prefix', 'tinyint')->setDefault(0);
			$table->addColumn('field_cache', 'mediumblob');
			$table->addColumn('review_field_cache', 'mediumblob');
			$table->addColumn('allow_anon_reviews', 'tinyint')->setDefault(0);
			$table->addColumn('allow_author_rating', 'tinyint')->setDefault(0);
			$table->addColumn('allow_pros_cons', 'tinyint')->setDefault(0);
			$table->addColumn('min_tags', 'smallint')->setDefault(0);
			$table->addColumn('allow_location', 'tinyint')->setDefault(0);
			$table->addColumn('require_cover_image', 'tinyint')->setDefault(0);
			$table->addColumn('layout_type', 'varchar', 25);
			$table->addKey(['parent_category_id', 'lft']);
			$table->addKey(['lft', 'rgt']);
		});
		
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('category_id', 'int')->after('blog_id');
		});
	}	
	
	public function upgrade2000011Step6(array $stepParams)
	{
		$sm = $this->schemaManager();

		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
		
		$perPage = 250;
		$db = $this->db();
		
		$blogEntryIds = $db->fetchAllColumn($db->limit(
			'
				SELECT blog_entry_id
				FROM xf_xa_ubs_blog_entry
				WHERE blog_entry_id > ?
				ORDER BY blog_entry_id
			', $perPage
		), $stepParams['position']);
		if (!$blogEntryIds)
		{
			return true;
		}
		
		$blogEntries = $db->fetchAll('
			SELECT *
			FROM xf_xa_ubs_blog_entry
			WHERE blog_entry_id IN (' . $db->quote($blogEntryIds) . ')
		');
		
		$db->beginTransaction();
		
		foreach ($blogEntries AS $blogEntry)
		{
			$blogEntry['categoriesList'] = $blogEntry['categories'] ? @unserialize($blogEntry['categories']) : array();
			if ($blogEntry['categoriesList'])
			{
				$categoryIds = array_keys($blogEntry['categoriesList']);
				
				$categoryId = reset($categoryIds);
				if ($categoryId)
				{
					$db->update('xf_xa_ubs_blog_entry', ['category_id' => $categoryId], 'blog_entry_id = ?', $blogEntry['blog_entry_id']);
				}	
			}
		}

		$db->commit();
		
		$stepParams['position'] = end($blogEntryIds);
		
		return $stepParams;
	}	
	
	public function upgrade2000011Step7()
	{
		$db = $this->db();

		$generalCategory = $db->fetchRow('
			SELECT *
			FROM xf_xa_ubs_category
			WHERE legacy_category_group = 2
		');
		
		if ($generalCategory)
		{
			$db->update('xf_xa_ubs_blog_entry', ['category_id' => $generalCategory['category_id']], 'category_id = ?', 0);
		}
	}	

	public function upgrade2000011Step8()
	{
		$sm = $this->schemaManager();
	
		$sm->createTable('xf_xa_ubs_category_field', function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('category_id', 'int');
			$table->addPrimaryKey(['field_id', 'category_id']);
			$table->addKey('category_id');
		});
	
		$sm->createTable('xf_xa_ubs_category_prefix', function(Create $table)
		{
			$table->addColumn('category_id', 'int');
			$table->addColumn('prefix_id', 'int');
			$table->addPrimaryKey(['category_id', 'prefix_id']);
			$table->addKey('prefix_id');
		});
	
		$sm->createTable('xf_xa_ubs_category_review_field', function(Create $table)
		{
			$table->addColumn('field_id', 'varbinary', 25);
			$table->addColumn('category_id', 'int');
			$table->addPrimaryKey(['field_id', 'category_id']);
			$table->addKey('category_id');
		});
		
		$sm->alterTable('xf_xa_ubs_category_watch', function(Alter $table)
		{		
			$table->addColumn('include_children', 'tinyint');
		});
		
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->dropColumns(['legacy_category_group', 'category_group_id']);
		});
		
		$sm->dropTable('xf_xa_ubs_category_group');
		$sm->dropTable('xf_xa_ubs_category_content');
	}
	
	public function upgrade2000011Step9()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->renameColumn('blog_title', 'title');
			$table->renameColumn('blog_meta_description', 'meta_description');
			$table->renameColumn('blog_description', 'message');
			$table->renameColumn('blog_create_date', 'create_date');
			$table->renameColumn('blog_last_update', 'last_update');
			$table->renameColumn('blog_edit_date', 'edit_date');
			$table->renameColumn('blog_likes', 'likes');
			$table->renameColumn('blog_like_users', 'like_users');
			$table->renameColumn('blog_view_count', 'view_count');
			$table->renameColumn('blog_attach_count', 'attach_count');
			$table->renameColumn('blog_tags', 'tags');
			$table->renameColumn('last_blog_entry', 'last_blog_entry_date');
			$table->renameColumn('blog_cover_image_id', 'cover_image_id');
			$table->renameColumn('blog_series_count', 'series_count');
		});	

		$sm->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->changeColumn('likes', 'int')->setDefault(0);
			$table->changeColumn('meta_description')->type('varchar')->length(320);
			
			$table->dropColumns(['blog_featured', 'blog_cover_image_cache', 'blog_fa_icon', 'blog_rate_review_system', 'blog_options', 'blog_entry_field_cache', 'review_field_cache']);
			$table->dropColumns(['blog_about_author', 'blog_image_attach_count', 'blog_file_attach_count', 'blog_entries_rating_count', 'blog_entries_rating_avg', 'blog_series_count']);
		});
		
		$sm->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->addColumn('sticky', 'tinyint')->setDefault(0)->after('message');
			$table->addColumn('layout_type', 'varchar', 25)->setDefault('list_view')->after('cover_image_id');
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});
	
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->dropColumns(['description', 'blog_entry_privacy', 'featured', 'categories', 'cover_image_cache', 'image_attach_count', 'file_attach_count']);
		});
	
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->renameColumn('blog_entry_view_count', 'view_count');
			$table->renameColumn('blog_entry_location', 'location');
			$table->renameColumn('custom_blog_entry_fields', 'custom_fields');
	
			$table->changeColumn('likes', 'int')->setDefault(0);
			$table->changeColumn('meta_description')->type('varchar')->length(320);
		});
	
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('description', 'varchar', 256)->setDefault('')->after('title');
			$table->addColumn('sticky', 'tinyint')->setDefault(0)->after('message');
			$table->addColumn('page_count', 'int')->setDefault(0)->after('comment_count');
			$table->addColumn('discussion_thread_id', 'int')->setDefault(0)->after('prefix_id');
			$table->addColumn('author_rating', 'float', '')->setDefault(0)->after('about_author');
			$table->addColumn('last_comment_id', 'int')->setDefault(0)->after('last_comment_date');
			$table->addColumn('last_comment_user_id', 'int')->setDefault(0)->after('last_comment_id');
			$table->addColumn('last_comment_username', 'varchar', 50)->setDefault('')->after('last_comment_user_id');
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});
		
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->dropColumns('watch_key');
		});
	}

	public function upgrade2000011Step10()
	{
		$sm = $this->schemaManager();
			
		$sm->dropTable('xf_xa_ubs_blog_entry_view');
	
		$sm->createTable('xf_xa_ubs_blog_entry_view', function(Create $table)
		{
			$table->addColumn('blog_entry_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('blog_entry_id');
		});

		$sm->createTable('xf_xa_ubs_blog_feature', function(Create $table)
		{
			$table->addColumn('blog_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('blog_id');
			$table->addKey('feature_date');
		});
		
		$sm->createTable('xf_xa_ubs_blog_entry_feature', function(Create $table)
		{
			$table->addColumn('blog_entry_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('blog_entry_id');
			$table->addKey('feature_date');
		});
		
		$sm->createTable('xf_xa_ubs_blog_entry_page', function(Create $table)
		{
			$table->addColumn('page_id', 'int')->autoIncrement();
			$table->addColumn('blog_entry_id', 'int');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('page_state', 'enum')->values(['visible','deleted', 'draft'])->setDefault('visible');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('title', 'varchar', 150)->setDefault('');
			$table->addColumn('nav_title', 'varchar', 150)->setDefault('');
			$table->addColumn('create_date', 'int')->setDefault(0);
			$table->addColumn('edit_date', 'int')->setDefault(0);
			$table->addColumn('depth', 'int')->setDefault(0);
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});
	}
		
	public function upgrade2000011Step11()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_comment', function(Alter $table)
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
	
		$sm->createTable('xf_xa_ubs_comment_read', function(Create $table)
		{
			$table->addColumn('comment_read_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('blog_entry_id', 'int');
			$table->addColumn('comment_read_date', 'int');
			$table->addUniqueKey(['user_id', 'blog_entry_id']);
			$table->addKey('blog_entry_id');
			$table->addKey('comment_read_date');
		});
	}	
		
	public function upgrade2000011Step12()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_blog_entry_rating', function(Alter $table)
		{
			$table->changeColumn('likes', 'int')->setDefault(0);
			$table->changeColumn('like_users', 'blob')->nullable(true);
			$table->changeColumn('custom_review_fields', 'mediumblob')->nullable(true);
		});	
		
		$sm->alterTable('xf_xa_ubs_blog_entry_rating', function(Alter $table)
		{
			$table->renameColumn('rate_review_id', 'rating_id');
			$table->renameColumn('rate_review_date', 'rating_date');
			$table->renameColumn('rate_review_state', 'rating_state');
			$table->renameColumn('pros_message', 'pros');
			$table->renameColumn('cons_message', 'cons');
			$table->renameColumn('summary_message', 'message');
			$table->renameColumn('owner_reply', 'author_response');
			$table->renameColumn('custom_review_fields', 'custom_fields');
	
			$table->addColumn('embed_metadata', 'blob')->nullable();
				
			$table->addKey(['blog_entry_id', 'rating_date']);
			$table->addKey(['user_id']);
	
			$table->dropColumns('review_title');
	
			$table->dropIndexes(['unique_rating', 'blog_entry_id']);
		});
	}	
	
	public function upgrade2000011Step13()
	{
		$sm = $this->schemaManager();
			
		$sm->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->renameColumn('series_name', 'title');
			$table->renameColumn('series_description', 'description');
			$table->renameColumn('series_create_date', 'create_date');
			$table->renameColumn('series_edit_date', 'edit_date');
			$table->renameColumn('series_part_count', 'part_count');
			$table->renameColumn('last_series_part_date', 'last_part_date');
			$table->renameColumn('last_series_part', 'last_part_id');
			$table->renameColumn('last_series_part_title', 'last_part_title');
			$table->renameColumn('last_series_part_entry_id', 'last_part_blog_entry_id');
	
			$table->addColumn('user_id', 'int')->after('series_id');
			$table->addColumn('community_series', 'tinyint')->setDefault(0);
			$table->addColumn('icon_date', 'int')->setDefault(0);
		});
	
		$sm->alterTable('xf_xa_ubs_series_part', function(Alter $table)
		{
			$table->renameColumn('series_part_id', 'part_id');
			$table->renameColumn('series_part', 'display_order');
			$table->renameColumn('series_part_title', 'title');
			$table->renameColumn('series_part_create_date', 'create_date');
			$table->renameColumn('series_part_edit_date', 'edit_date');
			
			$table->addColumn('user_id', 'int')->after('series_id');
		});
	
		$sm->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->dropColumns(['series_display_order', 'series_featured', 'series_parts_rating_count', 'series_parts_rating_avg']);
		});
		
		$sm->createTable('xf_xa_ubs_series_feature', function(Create $table)
		{
			$table->addColumn('series_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('series_id');
			$table->addKey('feature_date');
		});
	}	
	
	public function upgrade2000011Step14()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
		
		$series = $db->fetchAll('
			SELECT series.series_id, series.blog_id,
				blog.user_id as blog_user_id
			FROM xf_xa_ubs_series AS series
			INNER JOIN xf_xa_ubs_blog as blog
				ON (series.blog_id = blog.blog_id)
			WHERE series.user_id = 0
		');
		
		// update all the series records populating the user_id field with the owner of the blog associated with the series
		foreach ($series AS $seriesItem)
		{
			$db->update('xf_xa_ubs_series', ['user_id' => $seriesItem['blog_user_id']], 'series_id = ?', $seriesItem['series_id']);
		}	
		
		$seriesParts = $db->fetchAll('
			SELECT series_part.part_id, series_part.blog_id,
				blog.user_id as blog_user_id
			FROM xf_xa_ubs_series_part AS series_part
			INNER JOIN xf_xa_ubs_blog as blog
				ON (series_part.blog_id = blog.blog_id)
			WHERE series_part.user_id = 0
		');
		
		// TODO update all the series_part records populating the user_id field with the owner of the blog associated with the series
		foreach ($seriesParts AS $seriesPart)
		{
			$db->update('xf_xa_ubs_series_part', ['user_id' => $seriesPart['blog_user_id']], 'part_id = ?', $seriesPart['part_id']);
		}
		
		$sm->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->dropColumns('blog_id');
		});
		
		$sm->alterTable('xf_xa_ubs_series_part', function(Alter $table)
		{
			$table->dropColumns('blog_id');
		});
	}	
	
	public function upgrade2000011Step15(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
	
		$perPage = 250;
	
		$db = $this->db();
	
		$commentReplyIds = $db->fetchAllColumn($db->limit(
			'
				SELECT comment_reply_id
				FROM xf_xa_ubs_comment_reply
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
				comment.message as comment_message, comment.user_id as comment_user_id, comment.username as comment_username
			FROM xf_xa_ubs_comment_reply AS comment_reply
			INNER JOIN xf_xa_ubs_comment as comment
				ON (comment_reply.comment_id = comment.comment_id)
			WHERE comment_reply.comment_reply_id IN (' . $db->quote($commentReplyIds) . ')
		');
	
		$db->beginTransaction();
	
		foreach ($commentReplies AS $commentReply)
		{
			$quotedComment = $this->getQuoteWrapper($commentReply);
			$message = $quotedComment . $commentReply['message'];
	
			$this->db()->query("
				INSERT INTO xf_xa_ubs_comment
					(blog_entry_id, user_id, username, comment_date, comment_state, message, likes, like_users, warning_id, warning_message, ip_id)
				VALUES
					(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			", array($commentReply['blog_entry_id'], $commentReply['user_id'], $commentReply['username'], $commentReply['comment_reply_date'],
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
			. ', ubs-comment: ' . $commentReply['comment_id']
			. ', member: ' . $commentReply['comment_user_id']
			. '"]'
			. $commentReply['comment_message']
			. "[/QUOTE]\n";
	}
	
	public function upgrade2000011Step16()
	{
		$sm = $this->schemaManager();
	
		$sm->dropTable('xf_xa_ubs_comment_reply');
	
		$sm->alterTable('xf_xa_ubs_comment', function(Alter $table)
		{
			$table->dropColumns(['reply_count', 'first_reply_date', 'last_reply_date', 'latest_reply_ids']);
		});
	}
	
	public function upgrade2000011Step17(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
	
		$perPage = 250;
		$db = $this->db();
	
		$blogEntryIds = $db->fetchAllColumn($db->limit(
			'
				SELECT blog_entry_id
				FROM xf_xa_ubs_blog_entry
				WHERE blog_entry_id > ?
				ORDER BY blog_entry_id
			', $perPage
		), $stepParams['position']);
		if (!$blogEntryIds)
		{
			return true;
		}
	
		$db->beginTransaction();
	
		foreach ($blogEntryIds AS $blogEntryId)
		{
			$count = $db->fetchOne('
				SELECT  COUNT(*)
				FROM xf_xa_ubs_comment
				WHERE blog_entry_id = ?
				AND comment_state = \'visible\'
			', $blogEntryId);
	
			$db->update('xf_xa_ubs_blog_entry', ['comment_count' => intval($count)], 'blog_entry_id = ?', $blogEntryId);
		}
	
		$db->commit();
	
		$stepParams['position'] = end($blogEntryIds);
	
		return $stepParams;
	}	
	
	public function upgrade2000011Step18()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
	
		$sm->alterTable('xf_xa_ubs_blog_entry_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
		});
	
		foreach ($db->fetchAllKeyed("SELECT * FROM xf_xa_ubs_blog_entry_field", 'field_id') AS $fieldId => $field)
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
				$db->update('xf_xa_ubs_blog_entry_field', $update, 'field_id = ?', $fieldId);
			}
		}
	
		$sm->alterTable('xf_xa_ubs_blog_entry_field', function(Alter $table)
		{
			$table->dropColumns(['display_group_order', 'rating_type', 'match_regex', 'match_callback_class', 'match_callback_method']);
		});
	
		$sm->alterTable('xf_xa_ubs_blog_entry_field_value', function(Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
		});
	
		$this->query("
			UPDATE xf_xa_ubs_blog_entry_field
			SET field_type = 'stars'
			WHERE field_type = 'ratingselect'
		");
	
		$this->query("
			UPDATE xf_xa_ubs_blog_entry_field
			SET field_type = 'textbox'
			WHERE field_type = 'datepicker'
		");
	}
	
	public function upgrade2000011Step19()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
	
		$sm->alterTable('xf_xa_ubs_review_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
		});
	
		foreach ($db->fetchAllKeyed("SELECT * FROM xf_xa_ubs_review_field", 'field_id') AS $fieldId => $field)
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
				$db->update('xf_xa_ubs_review_field', $update, 'field_id = ?', $fieldId);
			}
		}
	
		$sm->alterTable('xf_xa_ubs_review_field', function(Alter $table)
		{
			$table->dropColumns(['display_group_order', 'rating_type', 'display_in_block', 'match_regex', 'match_callback_class', 'match_callback_method']);
		});
	
		$sm->alterTable('xf_xa_ubs_review_field_value', function(Alter $table)
		{
			$table->renameColumn('rate_review_id', 'rating_id');
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
	
			$table->dropColumns('blog_entry_id');
		});
	
		$this->query("
			UPDATE xf_xa_ubs_review_field
			SET field_type = 'stars'
			WHERE field_type = 'ratingselect'
		");
	
		$this->query("
			UPDATE xf_xa_ubs_review_field
			SET field_type = 'textbox'
			WHERE field_type = 'datepicker'
		");
	}	
	
	public function upgrade2000011Step20()
	{
		$map = [
			'ubs_prefix_group_*' => 'ubs_blog_entry_prefix_group.*',
			'ubs_prefix_*' => 'ubs_blog_entry_prefix.*',
			'ubs_blog_entry_field_*_choice_*' => 'xa_ubs_blog_entry_field_choice.$1_$2',
			'ubs_blog_entry_field_*_desc' => 'xa_ubs_blog_entry_field_desc.*',
			'ubs_blog_entry_field_*' => 'xa_ubs_blog_entry_field_title.*',
			'ubs_review_field_*_choice_*' => 'xa_ubs_review_field_choice.$1_$2',
			'ubs_review_field_*_desc' => 'xa_ubs_review_field_desc.*',
			'ubs_review_field_*' => 'xa_ubs_review_field_title.*',
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
	
	public function upgrade2000011Step21()
	{
		$db = $this->db();
	
		// update prefix CSS classes to the new name
		$prefixes = $db->fetchPairs("
			SELECT prefix_id, css_class
			FROM xf_xa_ubs_blog_entry_prefix
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
				$db->update('xf_xa_ubs_blog_entry_prefix',
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
			FROM xf_xa_ubs_category_field
		");
		foreach ($entries AS $entry)
		{
			$fieldCache[$entry['category_id']][$entry['field_id']] = $entry['field_id'];
		}
	
		$db->beginTransaction();
	
		foreach ($fieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_xa_ubs_category',
				['field_cache' => serialize($cache)],
				'category_id = ?',
				$categoryId
			);
		}
	
		$db->commit();
	
		// update review field category cache format
		$reviewfieldCache = [];
	
		$entries = $db->fetchAll("
			SELECT *
			FROM xf_xa_ubs_category_review_field
		");
		foreach ($entries AS $entry)
		{
			$reviewfieldCache[$entry['category_id']][$entry['field_id']] = $entry['field_id'];
		}
	
		$db->beginTransaction();
	
		foreach ($reviewfieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_xa_ubs_category',
				['review_field_cache' => serialize($cache)],
				'category_id = ?',
				$categoryId
			);
		}
	
		$db->commit();
	}	
	
	public function upgrade2000011Step22()
	{
		$db = $this->db();
	
		$associations = $db->fetchAll("
			SELECT cp.*
			FROM xf_xa_ubs_category_prefix AS cp
			INNER JOIN xf_xa_ubs_blog_entry_prefix as p ON
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
				'xf_xa_ubs_category',
				['prefix_cache' => serialize($prefixes)],
				'category_id = ?',
				$categoryId
			);
		}
	
		$db->commit();
	}	

	public function upgrade2000011Step23(array $stepParams)
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
				],
				'content_types' => [
					'ubs_review',
					'ubs_comment_reply'
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
				],
				'content_types' => [
					'ubs_review',
					'ubs_comment_reply'
				]
			], $stepParams);
		}	
		
		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');
	
		if (!$stepParams['content_type_tables'])
		{
			$columns = [];
	
			$oldType = 'ubs_review';
			$oldLen = strlen($oldType);
				
			$newType = 'ubs_rating';
			$newLen = strlen($newType);
	
			$columns[] = 'data = REPLACE(data, \'s:' . $oldLen .  ':"' . $oldType . '"\', \'s:' . $newLen . ':"' . $newType . '"\')';
			$this->query('
				UPDATE xf_spam_cleaner_log
				SET ' . implode(",\n", $columns)
			);
			return true;
		}
	
		foreach ($stepParams['content_type_tables'] AS $table => $null)
		{
			foreach ($stepParams['content_types'] AS $contentType)
			{
				if ($contentType == 'ubs_review')
				{
					$db->update($table, ['content_type' => 'ubs_rating'], 'content_type = ?', $contentType);
				}
			}
	
			unset ($stepParams['content_type_tables'][$table]);
	
			if ($maxRunTime && microtime(true) - $startTime > $maxRunTime)
			{
				break;
			}
		}
	
		return $stepParams;
	}	
	
	public function upgrade2000011Step24()
	{ 
		$sm = $this->schemaManager();

		$sm->createTable('xf_xa_ubs_feed', function(Create $table)
		{
			$table->addColumn('feed_id', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 250);
			$table->addColumn('url', 'varchar', 2083);
			$table->addColumn('frequency', 'int')->setDefault(1800);
			$table->addColumn('category_id', 'int');
			$table->addColumn('blog_id', 'int');
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('prefix_id', 'int')->setDefault(0);
			$table->addColumn('title_template', 'varchar', 250)->setDefault('');
			$table->addColumn('message_template', 'mediumtext');
			$table->addColumn('blog_entry_visible', 'tinyint')->setDefault(1);
			$table->addColumn('last_fetch', 'int')->setDefault(0);
			$table->addColumn('active', 'int')->setDefault(0);
			$table->addKey('active');
		});
		
		$sm->createTable('xf_xa_ubs_feed_log', function(Create $table)
		{
			$table->addColumn('feed_id', 'int');
			$table->addColumn('unique_id', 'varbinary', 250);
			$table->addColumn('hash', 'char', 32)->comment('MD5(title + content)');
			$table->addColumn('blog_entry_id', 'int');
			$table->addPrimaryKey(['feed_id', 'unique_id']);
		});
	}

	public function upgrade2000011Step25()
	{
		$db = $this->db();
		
		$this->query("
			UPDATE xf_xa_ubs_blog_entry
			SET last_update = publish_date
			WHERE last_update = 0
		");
		
		$this->query("
			UPDATE xf_xa_ubs_blog_entry
			SET edit_date = last_update
			WHERE edit_date = 0
		");
	}
	
	public function upgrade2000011Step26()
	{
		$this->insertNamedWidget('xa_ubs_latest_comments');
		$this->insertNamedWidget('xa_ubs_latest_reviews');
		$this->insertNamedWidget('xa_ubs_blogs_statistics');
	}
	
	
	// ################################ UPGRADE TO 2.0.0 Beta 4 ################## 
	
	public function upgrade2000034Step1()
	{
		$db = $this->db();
		$sm = $this->schemaManager();
		
		$this->query("UPDATE xf_xa_ubs_blog_entry_rating SET rating = round(rating, 0) ");

		$sm->alterTable('xf_xa_ubs_blog_entry_rating', function(Alter $table)
		{
			$table->changeColumn('rating', 'tinyint');
		});
		
		$sm->alterTable('xf_xa_ubs_blog_entry_rating', function(Alter $table)
		{
			$table->addColumn('last_edit_date', 'int')->setDefault(0)->after('attach_count');
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0)->after('last_edit_date');
			$table->addColumn('edit_count', 'int')->setDefault(0)->after('last_edit_user_id');
		});
	}
	
	
	// ################################ UPGRADE TO 2.0.1 ##################
	
	public function upgrade2000170Step1()
	{
		$sm = $this->schemaManager();	
		
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('cover_image_header', 'tinyint')->setDefault(0)->after('cover_image_id');
			$table->addColumn('has_poll', 'tinyint')->setDefault(0)->after('location');
		});
		
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('allow_poll', 'tinyint')->setDefault(0)->after('allow_location');
		});
		
		// fixes an issue where last_edit_date was being set when content was created
		$this->query("UPDATE xf_xa_ubs_blog SET last_edit_date = 0 WHERE edit_count = 0");
		$this->query("UPDATE xf_xa_ubs_blog_entry SET last_edit_date = 0 WHERE edit_count = 0");
		$this->query("UPDATE xf_xa_ubs_blog_entry_page SET last_edit_date = 0 WHERE edit_count = 0");
		$this->query("UPDATE xf_xa_ubs_blog_entry_rating SET last_edit_date = 0 WHERE edit_count = 0");
	}

	
	// ################################ UPGRADE TO 2.0.2 ##################
	
	public function upgrade2000270Step1()
	{
		$sm = $this->schemaManager();
		
		// Some TITLE lengths that should be 150 may be incorrectly set to 100, so lets force a change of 150 on all of them to make sure they are all set to the correct length of 150.
		$sm->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
			$table->changeColumn('last_blog_entry_title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ubs_blog_entry_page', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
			$table->changeColumn('nav_title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->changeColumn('last_blog_entry_title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
			$table->changeColumn('last_part_title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ubs_series_part', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
		});
		
		// alter the xa_ums_about_author field in the xf_user_profile table to make it NULLABLE (and default NULL).
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->changeColumn('xa_ubs_about_author', 'text')->nullable(true);
		});
		
		// drop the xfmg fields as we no longer use these
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->dropColumns(['xfmg_album_id', 'xfmg_media_ids', 'xfmg_video_ids']);
		});
	}	
	
	
	// ################################ UPGRADE TO 2.1.0 Beta 1 ##################
	
	public function upgrade2010031Step1(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
		
		return $this->entityColumnsToJson(
			'XenAddons\UBS:BlogItem', ['like_users', 'tags'], $position, $stepData
		);
	}
	
	public function upgrade2010031Step2(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
		
		return $this->entityColumnsToJson(
			'XenAddons\UBS:BlogEntryItem', ['like_users', 'custom_fields', 'tags'], $position, $stepData
		);
	}
	
	public function upgrade2010031Step3(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
		
		return $this->entityColumnsToJson(
			'XenAddons\UBS:BlogEntryRating', ['like_users', 'custom_fields'], $position, $stepData);
	}
	
	public function upgrade2010031Step4(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
		
		return $this->entityColumnsToJson(
			'XenAddons\UBS:Category', ['field_cache', 'review_field_cache', 'prefix_cache', 'breadcrumb_data'], $position, $stepData
		);
	}
	
	public function upgrade2010031Step5(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
		
		return $this->entityColumnsToJson('XenAddons\UBS:Comment', ['like_users'], $position, $stepData);
	}
	
	public function upgrade2010031Step6()
	{
		$this->migrateTableToReactions('xf_xa_ubs_blog');
	}
	
	public function upgrade2010031Step7()
	{
		$this->migrateTableToReactions('xf_xa_ubs_blog_entry');
	}
	
	public function upgrade2010031Step8()
	{
		$this->migrateTableToReactions('xf_xa_ubs_blog_entry_rating');
	}
	
	public function upgrade2010031Step9()
	{
		$this->migrateTableToReactions('xf_xa_ubs_comment');
	}
	
	public function upgrade2010031Step10()
	{
		$this->renameLikeAlertOptionsToReactions(['ubs_blog', 'ubs_blog_entry', 'ubs_comment', 'ubs_rating']);
	}
	
	public function upgrade2010031Step11()
	{
		$this->renameLikeAlertsToReactions(['ubs_blog', 'ubs_blog_entry', 'ubs_comment', 'ubs_rating']);
	}
	
	public function upgrade2010031Step12()
	{
		$this->renameLikePermissionsToReactions([
			'xa_ubs' => true // global and content
		], 'like');
		
		$this->renameLikePermissionsToReactions([
			'xa_ubs' => true // global and content
		], 'likeBlog');
	
		$this->renameLikePermissionsToReactions([
			'xa_ubs' => true // global and content
		], 'likeReview', 'reactReview');
	
		$this->renameLikePermissionsToReactions([
			'xa_ubs' => true // global and content
		], 'likeComment', 'reactComment');
	
		$this->renameLikeStatsToReactions(['blog_entry']);
	}
	
	
	// ################################ UPGRADE TO 2.1.4 ##################
	
	public function upgrade2010470Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('ratings_open', 'tinyint')->setDefault(1)->after('comments_open');
		});
		
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('blog_entry_list_order', 'varchar', 25)->setDefault('');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.5 ##################
	
	public function upgrade2010570Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->addColumn('create_date_timezone', 'varchar', 50)->setDefault('Europe/London')->after('create_date');
		});
	}
	
	// ################################ UPGRADE TO 2.1.6 ##################
	
	public function upgrade2010670Step1()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->addColumn('map_options', 'mediumblob');
		});
		
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('location_data', 'mediumblob')->after('location');
		});

		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('map_options', 'mediumblob');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.7 ##################
	
	public function upgrade2010770Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->addColumn('tags', 'mediumblob');
		});
	}
	
	public function upgrade2010770Step2()
	{	
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_ubs_blog_entry_field', function(Alter $table)
		{
			$table->addColumn('editable_user_group_ids', 'blob');
		});
	
		$db = $this->db();
		$db->beginTransaction();
	
		$fields = $db->fetchAll("
			SELECT *
			FROM xf_xa_ubs_blog_entry_field
		");
		foreach ($fields AS $field)
		{
			$update = '-1';
	
			$db->update('xf_xa_ubs_blog_entry_field',
				['editable_user_group_ids' => $update],
				'field_id = ?',
				$field['field_id']
			);
		}
	
		$db->commit();

		// drop all of the UBS 1.x fields that are no longer being used (They were used for a bespoke custom field searching and filtering system)
		$sm->alterTable('xf_xa_ubs_blog_entry_field', function(Alter $table)
		{
			$table->dropColumns(['is_searchable', 'is_filter_link', 'fs_description', 'allow_use_field_user_group_ids', 'allow_view_field_user_group_ids', 'allow_view_field_owner_in_user_group_ids']);
		});
	}
	
	public function upgrade2010770Step3()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_review_field', function(Alter $table)
		{
			$table->addColumn('editable_user_group_ids', 'blob');
		});
	
		$db = $this->db();
		$db->beginTransaction();
	
		$fields = $db->fetchAll("
			SELECT *
			FROM xf_xa_ubs_review_field
		");
		foreach ($fields AS $field)
		{
			$update = '-1';
	
			$db->update('xf_xa_ubs_review_field',
				['editable_user_group_ids' => $update],
				'field_id = ?',
				$field['field_id']
			);
		}
	
		$db->commit();
	
		// drop all of these UBS 1.x fields that are no longer being used (They were used for a bespoke custom field searching and filtering system)
		$sm->alterTable('xf_xa_ubs_review_field', function(Alter $table)
		{
			$table->dropColumns(['is_searchable', 'is_filter_link', 'fs_description', 'allow_view_field_user_group_ids']);
		});
	}

	
	// ################################ UPGRADE TO 2.1.8 ##################
	
	public function upgrade2010870Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('default_tags', 'mediumblob')->after('min_tags');
		});
	}	
	
	
	// ################################ UPGRADE TO 2.1.9 ##################
	
	public function upgrade2010970Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->renameColumn('cover_image_header', 'cover_image_above_blog_entry');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.11 ##################
	
	public function upgrade2011170Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->changeColumn('blog_entry_state', 'enum')->values(['visible','moderated','deleted','awaiting','draft'])->setDefault('visible');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.12 ##################
	
	public function upgrade2011270Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->addColumn('last_feature_date', 'int')->setDefault(0)->after('last_update');
		});
		
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('last_feature_date', 'int')->setDefault(0)->after('last_update');
		});
		
		$sm->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->addColumn('last_feature_date', 'int')->setDefault(0)->after('edit_date');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.13 (five steps) ##################
	
	public function upgrade2011370Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('overview_page_title', 'varchar', 150)->setDefault('')->after('page_count');
			$table->addColumn('overview_page_nav_title', 'varchar', 150)->setDefault('')->after('overview_page_title');
		});
	
		$sm->alterTable('xf_xa_ubs_blog_entry_page', function(Alter $table)
		{
			$table->addColumn('user_id', 'int')->setDefault(0)->after('blog_entry_id');
			$table->addColumn('username', 'varchar', 50)->setDefault('')->after('user_id');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('message');
			$table->addColumn('display_byline', 'tinyint')->setDefault(0)->after('nav_title');
			$table->addColumn('cover_image_id', 'int')->setDefault(0)->after('attach_count');
			$table->addColumn('cover_image_above_page', 'tinyint')->setDefault(0)->after('cover_image_id');
			$table->addColumn('has_poll', 'tinyint')->setDefault(0)->after('cover_image_above_page');
			$table->addColumn('reaction_score', 'int')->unsigned(false)->setDefault(0)->after('has_poll');
			$table->addColumn('reactions', 'blob')->nullable()->after('reaction_score');
			$table->addColumn('reaction_users', 'blob')->after('reactions');
			$table->addColumn('warning_id', 'int')->setDefault(0)->after('reaction_users');
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('')->after('warning_id');
			$table->addColumn('ip_id', 'int')->setDefault(0)->after('warning_message');
		});
	}
	
	public function upgrade2011370Step2()
	{
		$db = $this->db();
	
		$blogEntryPages = $db->fetchAll('
			SELECT page.page_id, page.blog_entry_id,
				blog_entry.blog_entry_id, blog_entry.user_id, blog_entry.username
			FROM xf_xa_ubs_blog_entry_page as page
			INNER JOIN xf_xa_ubs_blog_entry AS blog_entry ON
				(page.blog_entry_id = blog_entry.blog_entry_id)
		');
	
		$db->beginTransaction();
	
		foreach ($blogEntryPages AS $blogEntryPage)
		{
			$this->query("
				UPDATE xf_xa_ubs_blog_entry_page
				SET user_id = ?, username = ?
				WHERE page_id = ?
			", [$blogEntryPage['user_id'], $blogEntryPage['username'], $blogEntryPage['page_id']]);
		}
	
		$db->commit();
	}
	
	public function upgrade2011370Step3()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_blog_entry_page', function(Alter $table)
		{
			$table->addKey('user_id');
			$table->addKey(['blog_entry_id', 'create_date']);
			$table->addKey(['blog_entry_id', 'display_order']);
			$table->addKey('create_date');
		});
	}
	
	public function upgrade2011370Step4()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->addColumn('username', 'varchar', 50)->setDefault('')->after('user_id');
			$table->addColumn('series_state', 'enum')->values(['visible','moderated','deleted'])->setDefault('visible')->after('description');
			$table->addColumn('message', 'mediumtext')->after('series_state')->after('series_state');
			$table->addColumn('attach_count', 'smallint', 5)->setDefault(0);
			$table->addColumn('has_poll', 'tinyint')->setDefault(0)->after('attach_count');
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
		});
	}
	
	public function upgrade2011370Step5()
	{
		$sm = $this->schemaManager();
		$db = $this->db();
	
		$series = $db->fetchAll('
			SELECT series.series_id,
				user.user_id, user.username
			FROM xf_xa_ubs_series as series
			INNER JOIN xf_user AS user ON
				(series.user_id = user.user_id)
		');
	
		$db->beginTransaction();
	
		foreach ($series AS $seriesItem)
		{
			$this->query("
				UPDATE xf_xa_ubs_series
				SET username = ?
				WHERE series_id = ?
			", [$seriesItem['username'], $seriesItem['series_id']]);
		}
	
		$db->commit();
	}
	
	// ################################ UPGRADE TO 2.2.0 B1 ##################
	
	public function upgrade2020031Step1()
	{
		$this->addPrefixDescHelpPhrases([
			'ubs_blog_entry' => 'xf_xa_ubs_blog_entry_prefix'
		]);
	
		$this->insertThreadType('ubs_blog_entry', 'XenAddons\UBS:BlogEntryItem', 'XenAddons/UBS');
	}
	
	public function upgrade2020031Step2()
	{
		$this->createTable('xf_xa_ubs_blog_entry_contributor', function(Create $table)
		{
			$table->addColumn('blog_entry_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('is_co_author', 'tinyint')->setDefault(0);
			$table->addPrimaryKey(['blog_entry_id', 'user_id']);
			$table->addKey('user_id');
		});
	}
	
	public function upgrade2020031Step3()
	{
		$this->alterTable('xf_xa_ubs_blog_entry_field', function(Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});
	
		$this->alterTable('xf_xa_ubs_review_field', function(Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});
	}
	
	public function upgrade2020031Step4()
	{
		$this->alterTable('xf_xa_ubs_blog_entry_rating', function(Alter $table)
		{
			$table->addColumn('vote_score', 'int')->unsigned(false);
			$table->addColumn('vote_count', 'int')->setDefault(0);
			$table->addColumn('author_response_contributor_user_id', 'int')->setDefault(0)->after('message');
			$table->addColumn('author_response_contributor_username', 'varchar', 50)->setDefault('')->after('author_response_contributor_user_id');
			$table->addKey('author_response_contributor_user_id');
		});
	}
	
	public function upgrade2020031Step5()
	{
		$this->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('contributor_user_ids', 'blob')->after('username');
		});
	}
	
	public function upgrade2020031Step6()
	{
		$this->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('review_voting', 'varchar', 25)->setDefault('')->after('allow_ratings');
			$table->addColumn('allow_contributors', 'tinyint')->setDefault(0)->after('allow_blog_entries');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.0 B3 ##################
	
	public function upgrade2020033Step1()
	{
		$this->addPrefixDescHelpPhrases([
			'ubs_blog_entry' => 'xf_xa_ubs_blog_entry_prefix'
		]);
	}

	
	// ################################ UPGRADE TO 2.2.4 ##################
	
	public function upgrade2020470Step1()
	{
		$this->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('thread_set_blog_entry_tags', 'tinyint')->setDefault(0)->after('thread_prefix_id');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.5 ##################
	
	public function upgrade2020570Step1()
	{
		$this->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('expand_category_nav', 'tinyint')->setDefault(0);
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.8 ##################
	
	public function upgrade2020870Step1()
	{
		$this->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('content_term', 'varchar', 100)->setDefault('')->after('content_title');
			$table->addColumn('display_location_on_list', 'tinyint')->setDefault(0);
			$table->addColumn('location_on_list_display_type', 'varchar', 50);
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.10 ##################
	
	public function upgrade2021070Step1()
	{
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_ubs_comment_count', 'int')->setDefault(0)->after('xa_ubs_series_count');
			$table->addKey('xa_ubs_comment_count', 'ubs_comment_count');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.15 ##################
	
	public function upgrade2021570Step1()
	{	
		$this->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->addColumn('is_private', 'tinyint')->setDefault(0)->after('message');
			$table->addColumn('private_blog_entry_count', 'tinyint')->setDefault(0)->after('blog_entry_count');
		});
		
		$this->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('is_private', 'tinyint')->setDefault(0)->after('message');
		});
	}	
	
	
	// ################################ UPGRADE TO 2.2.16 ##################
	
	public function upgrade2021670Step1()
	{
		$this->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('description', 'varchar', 256)->setDefault('')->after('meta_title');
			$table->addColumn('page_count', 'int')->setDefault(0)->after('view_count');
			$table->addColumn('allow_index', 'enum')->values(['allow', 'deny', 'criteria'])->setDefault('allow');
			$table->addColumn('index_criteria', 'blob');
		});
	}
	
	public function upgrade2021670Step2()
	{
		$this->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
		});
	
		$this->alterTable('xf_xa_ubs_blog_entry_page', function(Alter $table)
		{
			$table->addColumn('description', 'varchar', 256)->setDefault('')->after('message');
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
		});
	}
	
	public function upgrade2021670Step3()
	{
		$this->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('description');
		});
	}
	
	public function upgrade2021670Step4()
	{
		$this->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('description');
		});
	}
	
	public function upgrade2021670Step5()
	{	
		$this->createTable('xf_xa_ubs_blog_page', function(Create $table)
		{
			$table->addColumn('page_id', 'int')->autoIncrement();
			$table->addColumn('blog_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('username', 'varchar', 50)->setDefault('');
			$table->addColumn('message', 'mediumtext');
			$table->addColumn('page_state', 'enum')->values(['visible','deleted', 'draft'])->setDefault('visible');
			$table->addColumn('display_order', 'int')->setDefault(1);
			$table->addColumn('title', 'varchar', 150)->setDefault('');
			$table->addColumn('nav_title', 'varchar', 150)->setDefault('');
			$table->addColumn('create_date', 'int')->setDefault(0);
			$table->addColumn('edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_date', 'int')->setDefault(0);
			$table->addColumn('last_edit_user_id', 'int')->setDefault(0);
			$table->addColumn('edit_count', 'int')->setDefault(0);
			$table->addColumn('attach_count', 'int')->setDefault(0);
			$table->addColumn('ip_id', 'int')->setDefault(0);
			$table->addColumn('embed_metadata', 'blob')->nullable();
			$table->addKey('user_id');
			$table->addKey(['blog_id', 'create_date']);
			$table->addKey(['blog_id', 'display_order']);
			$table->addKey('create_date');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.18 ##################
	
	public function upgrade2021870Step1()
	{
		$this->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->changeColumn('content_image', 'varchar', 200);
		});
	
		$this->alterTable('xf_xa_ubs_category', function(Alter $table)
		{
			$table->renameColumn('content_image', 'content_image_url');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.19 ##################
	
	public function upgrade2021970Step1()
	{
		$this->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addColumn('blog_entry_read_time', 'float', '')->setDefault(0)->after('blog_entry_state');
		});
	}
	
	public function upgrade2021970Step2()
	{
		$this->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->renameColumn('part_count', 'blog_entry_count');
		});
	
		$this->alterTable('xf_xa_ubs_series', function(Alter $table)
		{
			$table->dropColumns(['last_part_title']);
		});
	}
	
	public function upgrade2021970Step3()
	{
		$this->alterTable('xf_xa_ubs_series_part', function(Alter $table)
		{
			$table->renameColumn('part_id', 'series_part_id');
		});
	
		$this->alterTable('xf_xa_ubs_series_part', function(Alter $table)
		{
			$table->dropColumns(['title']);
		});
	}

	
	// ################################ UPGRADE TO 2.2.22 ##################
	
	public function upgrade2022270Step1()
	{	
		// Lets drop all of these XF1 legacy indexes and add new ones with new index names... 
		$this->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{		
			$table->dropIndexes(['user_id_blog_last_update', 'user_id_blog_create_date', 'blog_last_update', 'blog_create_date']);
		});
		
		$this->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{	
			$table->addKey(['user_id', 'create_date'], 'user_id_create_date');
			$table->addKey(['user_id', 'last_update'], 'user_id_last_update');
			$table->addKey('last_update');
			$table->addKey('create_date');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.23 ##################
	
	public function upgrade2022370Step1()
	{	
		// Lets drop all of these XF1 legacy indexes and add new ones with new index names...
		$this->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->dropIndexes(['blog_id_publish_date']);
		});
		
		$this->alterTable('xf_xa_ubs_blog_entry', function(Alter $table)
		{
			$table->addKey(['blog_id', 'publish_date'], 'blog_publish_date');
			$table->addKey(['blog_id', 'last_update'], 'blog_last_update');
			$table->addKey(['blog_id', 'rating_weighted'], 'blog_rating_weighted');
			$table->addKey(['category_id', 'publish_date'], 'category_publish_date');
			$table->addKey(['category_id', 'last_update'], 'category_last_update');
			$table->addKey(['category_id', 'rating_weighted'], 'category_rating_weighted');
			$table->addKey(['user_id', 'publish_date'], 'user_id_publish_date');
		});		
	}
	

	// ################################ UPGRADE TO 2.2.28 ##################
	
	public function upgrade2022870Step1()
	{
		$this->alterTable('xf_xa_ubs_blog', function(Alter $table)
		{
			$table->addColumn('has_poll', 'tinyint')->setDefault(0)->after('sticky');
		});
		
		$this->alterTable('xf_xa_ubs_blog_entry', function (Alter $table)
		{
			$table->dropColumns(['overview_page_nav_title']);
		});
		
		$this->alterTable('xf_xa_ubs_blog_entry_page', function (Alter $table)
		{
			$table->dropColumns(['nav_title']);
		});		
	}
	

	// ################################ UPGRADE TO 2.2.29 ##################
	
	public function upgrade2022970Step1()
	{
		$this->alterTable('xf_xa_ubs_category', function (Alter $table)
		{
			$table->addColumn('require_location', 'tinyint')->setDefault(0)->after('allow_location');
		});
	}
	
	public function upgrade2022970Step2()
	{	
		// Lets run this to make sure that everone has this index on their xa_user table!
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addKey('xa_ubs_series_count', 'ubs_series_count');
		});
	}

	
	// ################################ UPGRADE TO 2.2.30 #################
		
	public function upgrade2023070Step1()
	{
		$this->alterTable('xf_xa_ubs_blog_entry', function (Alter $table)
		{
			$table->addColumn('cover_image_caption', 'varchar', 500)->setDefault('')->after('cover_image_id');
		});
	}
	
	public function upgrade2023070Step2()
	{
		$this->alterTable('xf_xa_ubs_blog_entry_page', function (Alter $table)
		{
			$table->addColumn('cover_image_caption', 'varchar', 500)->setDefault('')->after('cover_image_id');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.35 #################
	
	public function upgrade2023570Step1()
	{
		$this->alterTable('xf_xa_ubs_blog', function (Alter $table)
		{
			$table->addColumn('blog_open', 'tinyint')->setDefault(1)->after('is_private');
		});
		
		$this->alterTable('xf_xa_ubs_blog_entry', function (Alter $table)
		{
			$table->addColumn('blog_entry_open', 'tinyint')->setDefault(1)->after('last_review_date');
		});
	}
	
	public function upgrade2023570Step2()
	{
		$this->createTable('xf_xa_ubs_series_view', function(Create $table)
		{
			$table->addColumn('series_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('series_id');
		});
		
		$this->alterTable('xf_xa_ubs_series', function (Alter $table)
		{		
			$table->addColumn('view_count', 'int')->setDefault(0)->after('tags');
			$table->addColumn('watch_count', 'int')->setDefault(0)->after('view_count');
		});
	}
	
	public function upgrade2023570Step3()
	{
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_ubs_review_count', 'int')->setDefault(0)->after('xa_ubs_comment_count');
			$table->addKey('xa_ubs_review_count', 'ubs_review_count');
		});
	}
	
	public function upgrade2023570Step4()
	{
		$widgetOptions = [
			'limit' => 10,
			'style' => 'list-view',
			'require_cover_or_content_image' => true
		];
	
		$this->insertNamedWidget('xa_ubs_whats_new_overview_lastest_entries', $widgetOptions);
	}
	
	
	// ################################ UBS 2.3.x for XF 2.3.x #############################################################################
	
	
	// ################################ UPGRADE TO 2.3.0 Alpha 1 ##################
	
	public function upgrade2030011Step1()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaUbsAllowedFileExtensions\'
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
				WHERE option_id = \'xaUbsAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade2030011Step2()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaUbsCommentAllowedFileExtensions\'
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
				WHERE option_id = \'xaUbsCommentAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade2030011Step3()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaUbsReviewAllowedFileExtensions\'
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
				WHERE option_id = \'xaUbsReviewAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade2030011Step4(): void
	{
		$tables = [
			'xf_xa_ubs_blog_entry_view',
			'xf_xa_ubs_series_view',
		];
	
		foreach ($tables AS $tableName)
		{
			$this->alterTable($tableName, function (Alter $table)
			{
				$table->engine('InnoDB');
			});
		}
	}
	
	public function upgrade2030011Step5(): void
	{
		$this->alterTable('xf_xa_ubs_series', function (Alter $table)
		{
			$table->addColumn('icon_optimized', 'tinyint')->setDefault(0)->after('icon_date');
		});
	}

	
	// ################################ UPGRADE TO 2.3.0 Release Candidate 5 ##################
	
	public function upgrade2030055Step1()
	{
		$this->insertNamedWidget('xa_ubs_trending_blog_entries');
	}
	
	
	// ################################ UPGRADE TO 2.3.4 ##################
	
	public function upgrade2030470Step1()
	{
		$this->alterTable('xf_xa_ubs_blog_entry_rating', function (Alter $table)
		{
			$table->addColumn('title', 'varchar', 100)->setDefault('')->after('rating');
		});
	}	
	
	
	// ################################ UPGRADE TO 2.3.7 ##################
	
	public function upgrade2030770Step1()
	{
		$this->alterTable('xf_xa_ubs_category', function (Alter $table)
		{
			$table->addColumn('auto_feature', 'tinyint')->setDefault(0)->after('location_on_list_display_type');
		});
	}
	
	public function upgrade2030770Step2()
	{
		$this->alterTable('xf_xa_ubs_blog', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
		
		$this->alterTable('xf_xa_ubs_blog_entry', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade2030770Step3()
	{
		$this->alterTable('xf_xa_ubs_series', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade2030770Step4(): void
	{
		$db = $this->db();
	
		$featuredBlogs = $db->fetchAllKeyed(
				'SELECT blog.*, blog_feature.*
			FROM xf_xa_ubs_blog AS blog
			INNER JOIN xf_xa_ubs_blog_feature AS blog_feature
				ON (blog_feature.blog_id = blog.blog_id)
			ORDER BY blog.blog_id ASC',
				'blog_id'
		);
	
		$rows = [];
		foreach ($featuredBlogs AS $blogId => $blog)
		{
			$rows[] = [
				'content_type' => 'ubs_blog',
				'content_id' => $blogId,
				'content_container_id' => 0,
				'content_user_id' => $blog['user_id'],
				'content_username' => $blog['username'],
				'content_date' => $blog['create_date'],
				'content_visible' => ($blog['blog_state'] === 'visible'),
				'feature_user_id' => $blog['user_id'],
				'feature_date' => $blog['feature_date'],
				'snippet' => '',
			];
		}
	
		if (!empty($rows))
		{
			$db->beginTransaction();
	
			$db->insertBulk('xf_featured_content', $rows);
			$db->update(
				'xf_xa_ubs_blog',
				['featured' => 1],
				'blog_id IN (' . $db->quote(array_keys($featuredBlogs)) . ')'
			);
	
			$db->commit();
		}
	}
	
	public function upgrade2030770Step5(): void
	{
		$db = $this->db();
	
		$featuredBlogEntries = $db->fetchAllKeyed(
				'SELECT blog_entry.*, blog_entry_feature.*
			FROM xf_xa_ubs_blog_entry AS blog_entry
			INNER JOIN xf_xa_ubs_blog_entry_feature AS blog_entry_feature
				ON (blog_entry_feature.blog_entry_id = blog_entry.blog_entry_id)
			ORDER BY blog_entry.blog_entry_id ASC',
				'blog_entry_id'
		);
	
		$rows = [];
		foreach ($featuredBlogEntries AS $blogEntryId => $blogEntry)
		{
			$rows[] = [
				'content_type' => 'ubs_blog_entry',
				'content_id' => $blogEntryId,
				'content_container_id' => $blogEntry['category_id'],
				'content_user_id' => $blogEntry['user_id'],
				'content_username' => $blogEntry['username'],
				'content_date' => $blogEntry['publish_date'],
				'content_visible' => ($blogEntry['blog_entry_state'] === 'visible'),
				'feature_user_id' => $blogEntry['user_id'],
				'feature_date' => $blogEntry['feature_date'],
				'snippet' => '',
			];
		}
	
		if (!empty($rows))
		{
			$db->beginTransaction();
	
			$db->insertBulk('xf_featured_content', $rows);
			$db->update(
				'xf_xa_ubs_blog_entry',
				['featured' => 1],
				'blog_entry_id IN (' . $db->quote(array_keys($featuredBlogEntries)) . ')'
			);
	
			$db->commit();
		}
	}
		
	public function upgrade2030770Step6(): void
	{
		$db = $this->db();
	
		$featuredSeries = $db->fetchAllKeyed(
				'SELECT series.*, series_feature.*
			FROM xf_xa_ubs_series AS series
			INNER JOIN xf_xa_ubs_series_feature AS series_feature
				ON (series_feature.series_id = series.series_id)
			ORDER BY series.series_id ASC',
				'series_id'
		);
	
		$rows = [];
		foreach ($featuredSeries AS $seriesId => $series)
		{
			$rows[] = [
				'content_type' => 'ubs_series',
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
				'xf_xa_ubs_series',
				['featured' => 1],
				'series_id IN (' . $db->quote(array_keys($featuredSeries)) . ')'
			);
	
			$db->commit();
		}
	}
	
	public function upgrade2030770Step7(): void
	{
		$this->dropTable('xf_xa_ubs_blog_entry_feature');
		
		$this->dropTable('xf_xa_ubs_blog_feature');
		
		$this->dropTable('xf_xa_ubs_series_feature');
	}	
	
	
	
	
	
	
	
	
	
	
	
	// ############################################ FINAL UPGRADE ACTIONS ##########################
	
	public function postUpgrade($previousVersion, array &$stateChanges)
	{
		if ($this->applyDefaultPermissions($previousVersion))
		{
			// since we're running this after data imports, we need to trigger a permission rebuild
			// if we changed anything
			$this->app->jobManager()->enqueueUnique(
				'permissionRebuild',
				'XF:PermissionRebuild',
				[],
				false
			);
		}
	
		if ($previousVersion && $previousVersion < 2000010)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeBlogEmbedMetadataRebuild',
				'XenAddons\UBS:UbsBlogEmbedMetadata',
				['types' => 'attachments'],
				false
			);
			
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeBlogEntryEmbedMetadataRebuild',
				'XenAddons\UBS:UbsBlogEntryEmbedMetadata',
				['types' => 'attachments'],
				false
			);
				
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeBlogEntryPageEmbedMetadataRebuild',
				'XenAddons\UBS:UbsBlogEntryPageEmbedMetadata',
				['types' => 'attachments'],
				false
			);
				
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeCommentEmbedMetadataRebuild',
				'XenAddons\UBS:UbsCommentEmbedMetadata',
				['types' => 'attachments'],
				false
			);
				
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeReviewEmbedMetadataRebuild',
				'XenAddons\UBS:UbsReviewEmbedMetadata',
				['types' => 'attachments'],
				false
			);
			
			/** @var \XF\Service\RebuildNestedSet $service */
			$service = \XF::service('XF:RebuildNestedSet', 'XenAddons\UBS:Category', [
				'parentField' => 'parent_category_id'
			]);
			$service->rebuildNestedSetInfo();
	
			$likeContentTypes = [
				'ubs_blog',
				'ubs_blog_entry',
				'ubs_comment',
				'ubs_rating'
			];
			foreach ($likeContentTypes AS $contentType)
			{
				$this->app->jobManager()->enqueueUnique(
					'xa_ubsUpgradeLikeIsCountedRebuild_' . $contentType,
					'XF:LikeIsCounted',
					['type' => $contentType],
					false
				);
			}
		}
		
		
		if ($previousVersion && $previousVersion < 2020870)
		{
			$this->app->jobManager()->enqueueUnique(
				'ubsRebuildBlogEntryLocationData',
				'XenAddons\UBS:BlogEntryLocationData',
				[],
				false
			);
		}		

		if ($previousVersion && $previousVersion < 2021070)
		{			
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeUserCountRebuild',
				'XenAddons\UBS:UserCount',
				[],
				false
			);
		}	
		
		if ($previousVersion && $previousVersion < 2021970)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeBlogEntryItemRebuild',
				'XenAddons\UBS:BlogEntryItem',
				[],
				false
			);
		}

		if ($previousVersion && $previousVersion < 2022170)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeSeriesPartRebuild',
				'XenAddons\UBS:SeriesPart',
				[],
				false
			);
		}
		
		if ($previousVersion && $previousVersion < 2023570)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_ubsUpgradeUserCountRebuild',
				'XenAddons\UBS:UserCount',
				[],
				false
			);
		}

		// the following runs after every upgrade		
		\XF::repository('XenAddons\UBS:BlogEntryPrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\UBS:BlogEntryField')->rebuildFieldCache();
		\XF::repository('XenAddons\UBS:ReviewField')->rebuildFieldCache();

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
			$table->dropColumns(['xa_ubs_blog_count', 'xa_ubs_blog_entry_count', 'xa_ubs_series_count', 'xa_ubs_comment_count', 'xa_ubs_review_count']);
		});
		
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->dropColumns('xa_ubs_about_author');
		});
	}
	
	public function uninstallStep3()
	{
		$db = $this->db();
	
		$contentTypes = [
			'ubs_blog', 
			'ubs_blog_entry', 
			'ubs_blog_page', 
			'ubs_category', 
			'ubs_comment', 
			'ubs_page', 
			'ubs_rating', 
			'ubs_series', 
			'ubs_series_part'
		];
		
		$this->uninstallContentTypeData($contentTypes);
	
		$db->beginTransaction();
	
		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'userBlogsSystem'");
		$db->delete('xf_permission_cache_content', "content_type = 'ubs_category'");
		$db->delete('xf_permission_entry', "permission_group_id = 'xa_ubs'");
		$db->delete('xf_permission_entry_content', "permission_group_id = 'xa_ubs'");
	
		$db->commit();
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	public function uninstallStep4()
	{
		$conditions = [
			['title', 'LIKE', 'ubs_blog_entry_%']
		];
	
		$phrases = $this->app->finder('XF:Phrase')
			->whereOr($conditions)
			->fetch();
	
		/** @var \XF\Entity\Phrase $phrase */
		foreach ($phrases as $phrase)
		{
			$phrase->delete(false);
		}
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
			'xa_ubs_trending_blog_entries' => function ($key, array $options = [])
			{
				$options = array_replace([
					'contextual' => true,
					'style' => 'simple',
					'content_type' => 'ubs_blog_entry',
				], $options);
					
				$this->createWidget(
					$key,
					'trending_content',
					[
						'positions' => [
							'xa_ubs_index_sidenav' => 150,
							'xa_ubs_category_sidenav' => 150,
						],
						'options' => $options,
					],
					'Trending blog entries'
				);
			},		
			'xa_ubs_latest_comments' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_ubs_latest_comments',
					[
						'positions' => [
							'xa_ubs_index_sidenav' => 300,
							'xa_ubs_category_sidenav' => 300
						],
						'options' => $options
					]
				);
			},
			'xa_ubs_latest_reviews' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_ubs_latest_reviews',
					[
						'positions' => [
							'xa_ubs_index_sidenav' => 400,
							'xa_ubs_category_sidenav' => 400
						],
						'options' => $options
					]
				);
			},
			'xa_ubs_blogs_statistics' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_ubs_blogs_statistics',
					[
						'positions' => ['xa_ubs_index_sidenav' => 1000],
						'options' => $options
					]
				);
			},
			'xa_ubs_whats_new_overview_lastest_entries' => function($key, array $options = [])
			{
				$options = array_replace([
					'limit' => 10,
					'style' => 'list-view',
					'require_cover_or_content_image' => true
				], $options);
					
				$this->createWidget(
					$key,
					'xa_ubs_latest_entries',
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
			// XenAddons\UBS: Blog permissions
			$this->applyGlobalPermission('xa_ubs', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'viewBlogAttach', 'general', 'viewNode');			
			$this->applyGlobalPermission('xa_ubs', 'reactBlog', 'forum', 'react');
			$this->applyGlobalPermission('xa_ubs', 'createBlog', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'addPageOwnBlog', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'submitBlogWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ubs', 'uploadBlogAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'editOwnBlog', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteOwnBlog', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'tagOwnBlog', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('xa_ubs', 'tagAnyBlog', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xa_ubs', 'manageOthersTagsOwnBlog', 'forum', 'manageOthersTagsOwnThread');
			$this->applyGlobalPermission('xa_ubs', 'setOwnBlogAsCommunityBlog', 'forum', 'postThread');
			
			// XenAddons\UBS: Blog moderator permissions
			$this->applyGlobalPermission('xa_ubs', 'viewModeratedBlog', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ubs', 'viewDeletedBlog', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ubs', 'viewPrivateBlog', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'approveUnapproveBlog', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'editAnyBlog', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteAnyBlog', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'hardDeleteAnyBlog', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'undeleteBlog', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ubs', 'reassignBlog', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'manageAnyBlogTag', 'forum', 'manageAnyTag');
			$this->applyGlobalPermission('xa_ubs', 'featureUnfeatureBlog', 'forum', 'stickUnstickThread');
			$this->applyGlobalPermission('xa_ubs', 'warnBlog', 'forum', 'warn');
			
			// XenAddons\UBS: Blog Entry permissions
			$this->applyGlobalPermission('xa_ubs', 'viewFull', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'viewBlogEntryAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'react', 'forum', 'react');
			$this->applyGlobalPermission('xa_ubs', 'add', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'addPageOwnBlogEntry', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'submitWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ubs', 'uploadBlogEntryAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'setAuthorRatingOwn', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'editOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteOwn', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'tagOwnBlogEntry', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('xa_ubs', 'tagAnyBlogEntry', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xa_ubs', 'manageOthersTagsOwnEntry', 'forum', 'manageOthersTagsOwnThread');
			$this->applyGlobalPermission('xa_ubs', 'lockUnlockCommentsOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'lockUnlockRatingsOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'votePoll', 'forum', 'votePoll');
			$this->applyGlobalPermission('xa_ubs', 'manageOwnContributors', 'forum', 'editOwnPost');

			// XenAddons\UBS: Blog Entry moderator permissions
			$this->applyGlobalPermission('xa_ubs', 'viewModerated', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ubs', 'viewDeleted', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ubs', 'viewDraft', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'viewPrivate', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'approveUnapprove', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'editAny', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteAny', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'hardDeleteAny', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'undelete', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ubs', 'reassign', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'manageAnyTag', 'forum', 'manageAnyTag');
			$this->applyGlobalPermission('xa_ubs', 'featureUnfeature', 'forum', 'stickUnstickThread');
			$this->applyGlobalPermission('xa_ubs', 'warn', 'forum', 'warn');
			$this->applyGlobalPermission('xa_ubs', 'blogEntryReplyBan', 'forum', 'threadReplyBan');
			$this->applyGlobalPermission('xa_ubs', 'manageAnyContributors', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'convertToThread', 'forum', 'manageAnyThread');
			
			// XenAddons\UBS: Series permissions
			$this->applyGlobalPermission('xa_ubs', 'viewSeries', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'viewSeriesAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'reactSeries', 'forum', 'react');
			$this->applyGlobalPermission('xa_ubs', 'createSeries', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'addSeriesWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ubs', 'uploadSeriesAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'addToCommunitySeries', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ubs', 'editOwnSeries', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteOwnSeries', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'tagOwnSeries', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('xa_ubs', 'tagAnySeries', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('xa_ubs', 'manageOthersTagsOwnSeries', 'forum', 'manageOthersTagsOwnThread');
			
			// XenAddons\UBS: Series moderator permissions
			$this->applyGlobalPermission('xa_ubs', 'viewModeratedSeries', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ubs', 'viewDeletedSeries', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ubs', 'approveUnapproveSeries', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'editAnySeries', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteAnySeries', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'hardDeleteAnySeries', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'undeleteSeries', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ubs', 'manageAnySeriesTag', 'forum', 'manageAnyTag');
			$this->applyGlobalPermission('xa_ubs', 'featureUnfeatureSeries', 'forum', 'stickUnstickThread');
			$this->applyGlobalPermission('xa_ubs', 'warnSeries', 'forum', 'warn');			
			
			// XenAddons\UBS: Comment permissions
			$this->applyGlobalPermission('xa_ubs', 'viewComments', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'viewCommentAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'reactComment', 'forum', 'react');
			$this->applyGlobalPermission('xa_ubs', 'addComment', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ubs', 'addCommentWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ubs', 'uploadCommentAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ubs', 'editComment', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteComment', 'forum', 'deleteOwnPost');
			
			// XenAddons\UBS: Comment moderator permissions
			$this->applyGlobalPermission('xa_ubs', 'viewModeratedComments', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ubs', 'viewDeletedComments', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ubs', 'approveUnapproveComment', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'editAnyComment', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteAnyComment', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'undeleteComment', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ubs', 'hardDeleteAnyComment', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'reassignComment', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'warnComment', 'forum', 'warn');
			
			// XenAddons\UBS: Review permissions
			$this->applyGlobalPermission('xa_ubs', 'viewReviews', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'viewReviewAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ubs', 'reactReview', 'forum', 'react');
			$this->applyGlobalPermission('xa_ubs', 'rate', 'forum', 'react');
			$this->applyGlobalPermission('xa_ubs', 'postReviewWithoutApproval', 'general', 'submitWithoutApproval');
			$this->applyGlobalPermission('xa_ubs', 'uploadReviewAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ubs', 'editReview', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteReview', 'forum', 'deleteOwnPost');
			$this->applyGlobalPermission('xa_ubs', 'reviewReply', 'forum', 'editOwnPost');
			
			// Note: Deliberately not adding "Review blog entries anonymously" or "Review blog entries multiple times" permissions as these are expected to be set manually as part of post install configuration steps.
			
			// XenAddons\UBS: Review moderator permissions
			$this->applyGlobalPermission('xa_ubs', 'viewModeratedReviews', 'forum', 'viewModerated');
			$this->applyGlobalPermission('xa_ubs', 'viewDeletedReviews', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('xa_ubs', 'approveUnapproveReview', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'editAnyReview', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'deleteAnyReview', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'undeleteReview', 'forum', 'undelete');
			$this->applyGlobalPermission('xa_ubs', 'hardDeleteAnyReview', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('xa_ubs', 'reassignReview', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('xa_ubs', 'warnReview', 'forum', 'warn');
						
			$applied = true;
		}
	
		if (!$previousVersion || $previousVersion < 2000011)
		{
			// XenAddons\UBS: Blog moderator permissions
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ubs', 'inlineModBlog', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnyBlog', 'undeleteBlog', 'approveUnapproveBlog', 'editAnyBlog')
			");
				
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ubs', 'inlineModBlog', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnyBlog', 'undeleteBlog', 'approveUnapproveBlog', 'editAnyBlog')
			");
			
			// XenAddons\UBS: Blog Entry moderator permissions
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ubs', 'inlineMod', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny')
			");
			
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ubs', 'inlineMod', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny')
			");
			
			// XenAddons\UBS: Series moderator permissions
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ubs', 'inlineModSeries', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnySeries', 'undeleteSeries', 'approveUnapproveSeries', 'editAnySeries')
			");
			
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ubs', 'inlineModSeries', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnySeries', 'undeleteSeries', 'approveUnapproveSeries', 'editAnySeries')
			");			

			// XenAddons\UBS: Comment moderator permissions
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ubs', 'inlineModComment', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnyComment', 'undeleteComment', 'approveUnapproveComment', 'editAnyComment')
			");
			
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ubs', 'inlineModComment', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnyComment', 'undeleteComment', 'approveUnapproveComment', 'editAnyComment')
			");	

			// XenAddons\UBS: Review moderator permissions
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ubs', 'inlineModReview', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnyReview', 'undeleteReview', 'approveUnapproveReview', 'editAnyReview')
			");
				
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ubs', 'inlineModReview', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ubs'
					AND permission_id IN ('deleteAnyReview', 'undeleteReview', 'approveUnapproveReview', 'editAnyReview')
			");
	
			$applied = true;
		}
	
		if (!$previousVersion || $previousVersion < 2020010)
		{
			// XenAddons\UBS: Blog Entry permissions
			$this->applyGlobalPermission('xa_ubs', 'manageOwnContributors', 'xa_ubs', 'editOwn');
			$this->applyContentPermission('xa_ubs', 'manageOwnContributors', 'xa_ubs', 'editOwn');
			
			// XenAddons\UBS: Blog Entry moderator permissions
			$this->applyGlobalPermission('xa_ubs', 'manageAnyContributors', 'xa_ubs', 'editAny');
			$this->applyContentPermission('xa_ubs', 'manageAnyContributors', 'xa_ubs', 'editAny');

			// XenAddons\UBS: Review permissions
			$this->applyGlobalPermission('xa_ubs', 'contentVote', 'xa_ubs', 'rate');
			$this->applyContentPermission('xa_ubs', 'contentVote', 'xa_ubs', 'rate');
			
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2020770)
		{
			// XenAddons\UBS: Blog permissions
			$this->applyGlobalPermission('xa_ubs', 'viewBlogMap', 'xa_ubs', 'view');
			$this->applyContentPermission('xa_ubs', 'viewBlogMap', 'xa_ubs', 'view');
			
			// XenAddons\UBS: Blog Entry permissions
			$this->applyGlobalPermission('xa_ubs', 'viewBlogEntryMap', 'xa_ubs', 'view');
			$this->applyContentPermission('xa_ubs', 'viewBlogEntryMap', 'xa_ubs', 'view');
			$this->applyGlobalPermission('xa_ubs', 'viewCategoryMap', 'xa_ubs', 'view');
			$this->applyContentPermission('xa_ubs', 'viewCategoryMap', 'xa_ubs', 'view');
				
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2022870)
		{
			// XenAddons\UBS: Blog permissions
			$this->applyGlobalPermission('xa_ubs', 'setEntryListLayoutType', 'xa_ubs', 'editOwnBlog');
			$this->applyContentPermission('xa_ubs', 'setEntryListLayoutType', 'xa_ubs', 'editOwnBlog');
			$this->applyGlobalPermission('xa_ubs', 'setMapOptions', 'xa_ubs', 'editOwnBlog');
			$this->applyContentPermission('xa_ubs', 'setMapOptions', 'xa_ubs', 'editOwnBlog');
			$this->applyGlobalPermission('xa_ubs', 'votePollBlog', 'forum', 'votePoll');
			$this->applyContentPermission('xa_ubs', 'votePollBlog', 'forum', 'votePoll');
		
			// XenAddons\UBS: Series permissions
			$this->applyGlobalPermission('xa_ubs', 'votePollSeries', 'forum', 'votePoll');
			$this->applyContentPermission('xa_ubs', 'votePollSeries', 'forum', 'votePoll');
			
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2023470)
		{
			// XenAddons\UBS: Blog entry permissions
			$this->applyGlobalPermission('xa_ubs', 'moveOwn', 'xa_ubs', 'editOwn');
			
			$applied = true;
		}
					
		return $applied;
	}	
}