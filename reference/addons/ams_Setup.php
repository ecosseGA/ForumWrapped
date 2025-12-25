<?php

namespace XenAddons\AMS;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;
use XF\Entity\Widget;
use XF\Service\RebuildNestedSet;
use XenAddons\AMS\Install\Data\MySql;

use XenAddons\AMS\Repository\ArticleField;
use XenAddons\AMS\Repository\ArticlePrefix;

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
			$table->addColumn('xa_ams_article_count', 'int')->setDefault(0);
			$table->addColumn('xa_ams_series_count', 'int')->setDefault(0);
			$table->addColumn('xa_ams_comment_count', 'int')->setDefault(0);
			$table->addColumn('xa_ams_review_count', 'int')->setDefault(0);
			$table->addKey('xa_ams_article_count', 'ams_article_count');
			$table->addKey('xa_ams_series_count', 'ams_series_count');
			$table->addKey('xa_ams_comment_count', 'ams_comment_count');
			$table->addKey('xa_ams_review_count', 'ams_review_count');
		});
		
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->addColumn('xa_ams_about_author', 'text')->nullable(true);
			$table->addColumn('xa_ams_author_name', 'text')->nullable(true);
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
		
		$this->insertThreadType('ams_article', 'XenAddons\AMS:ArticleItem', 'XenAddons/AMS');
	}
	
	public function installStep4()
	{
		$this->db()->query("
			REPLACE INTO `xf_route_filter`
				(`prefix`,`find_route`,`replace_route`,`enabled`,`url_to_route_only`)
			VALUES
				('ams', 'ams/', 'articles/', 1, 0);
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
		$service = \XF::service('XF:RebuildNestedSet', 'XenAddons\AMS:Category', [
			'parentField' => 'parent_category_id'
		]);
		$service->rebuildNestedSetInfo();
	
		\XF::repository('XenAddons\AMS:ArticlePrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\AMS:ArticleField')->rebuildFieldCache();
		\XF::repository('XenAddons\AMS:ReviewField')->rebuildFieldCache();
	}	
	
	
	// ################################ UPGRADE STEPS ####################	
	
	
	// ################################ UPGRADE TO AMS 1.1.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1010031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_article ADD tags MEDIUMBLOB NOT NULL");
		
		$this->query("
			ALTER TABLE xf_nflj_ams_category
				ADD allow_pros_cons tinyint(3) unsigned NOT NULL DEFAULT '0',
				ADD modular_layout_options mediumblob NOT NULL,
				ADD modular_home_limit INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD modular_cat_limit INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD min_tags SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				DROP modular_layout_type,
				DROP modular_limit
		");

		$this->query("UPDATE xf_nflj_ams_category SET allow_pros_cons = 1 WHERE rate_review_system = 2");
		$this->query("ALTER TABLE xf_nflj_ams_article DROP COLUMN article_tags");
	}

	// ################################ UPGRADE TO AMS 1.2.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1020031Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ams_category
				CHANGE category_name category_name varchar(100) NOT NULL,
				DROP COLUMN require_article_image
		");		
	}
		
	// ################################ UPGRADE TO AMS 1.2.0 Beta 2 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1020032Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ams_article
				ADD article_open tinyint(3) unsigned NOT NULL DEFAULT '1' AFTER article_state,
				ADD comments_open tinyint(3) unsigned NOT NULL DEFAULT '1',
				ADD last_edit_date int(10) unsigned NOT NULL DEFAULT '0',
				ADD last_edit_user_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD edit_count int(10) unsigned NOT NULL DEFAULT '0'
		");
	}
			
	// ################################ UPGRADE TO AMS 1.2.0 Beta 4 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1020034Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_article ADD cover_image_header tinyint(3) unsigned NOT NULL DEFAULT '0' AFTER cover_image_id");
	}
	
	// ################################ UPGRADE TO AMS 1.2.0 RC 2 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1020052Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_rate_review ADD attach_count int(10) unsigned NOT NULL DEFAULT '0' ");
	}	
	
	// ################################ UPGRADE TO AMS 1.3.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1030031Step1()
	{	
		$this->query("ALTER TABLE xf_nflj_ams_article ADD meta_description varchar(160) NOT NULL AFTER title");
		
		$this->query("
			ALTER TABLE xf_nflj_ams_comment_reply
				ADD comment_reply_state enum('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
				ADD likes int(10) unsigned NOT NULL DEFAULT '0',
				ADD like_users blob NOT NULL,
				ADD warning_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD warning_message varchar(255) NOT NULL DEFAULT ''
		");
		
		$this->query("
			ALTER TABLE xf_nflj_ams_article_page
				ADD nav_title varchar(100) NOT NULL DEFAULT '',
				ADD create_date int(10) unsigned NOT NULL DEFAULT '0',
				ADD edit_date int(10) unsigned NOT NULL DEFAULT '0',
				ADD depth int(10) unsigned NOT NULL DEFAULT '0',
				ADD last_edit_date int(10) unsigned NOT NULL DEFAULT '0',
				ADD last_edit_user_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD edit_count int(10) unsigned NOT NULL DEFAULT '0',
				CHANGE article_page_order display_order int(10) unsigned NOT NULL DEFAULT '1',
				CHANGE article_page_title title varchar(150) NOT NULL
		");
		
		$this->query("UPDATE xf_nflj_ams_article_page SET create_date = " . \XF::$time);
	}
		
	// ################################ UPGRADE TO AMS 1.3.6 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1030670Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_article ADD cover_image_cache BLOB NOT NULL");
	}
		
	// ################################ UPGRADE TO AMS 1.3.9 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1030970Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ams_article
				ADD image_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD file_attach_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				ADD article_location VARCHAR(50) NOT NULL DEFAULT '',
				ADD original_source MEDIUMBLOB NOT NULL
		");
		
		$this->query("ALTER TABLE xf_nflj_ams_custom_field ADD hide_title tinyint(3) unsigned NOT NULL DEFAULT '0' AFTER field_id");
	}
	
	// ################################ UPGRADE TO AMS 1.4.1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1040170Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_category ADD original_source_required tinyint(3) unsigned NOT NULL DEFAULT '0'");
	}
		
	// ################################ UPGRADE TO AMS 1.4.3 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1040370Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_category ADD original_source_enabled tinyint(3) unsigned NOT NULL DEFAULT '0' AFTER min_tags");
		$this->query("UPDATE xf_nflj_ams_category SET original_source_enabled = '1' WHERE original_source_required = '1'");
	}
		
	// ################################ UPGRADE TO AMS 1.5.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1050031Step1()
	{
		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ams_series (
				series_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				user_id int(10) unsigned NOT NULL,
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
				last_series_part_article_id int(10) unsigned NOT NULL DEFAULT '0',
				series_parts_rating_count int(10) unsigned NOT NULL DEFAULT '0',
				series_parts_rating_avg float unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (series_id),
				KEY series_display_order (series_display_order),
				KEY series_name (series_name),
				KEY user_id (user_id),
				KEY series_parts_rating_avg (series_parts_rating_avg)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
		");	

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ams_series_part (
				series_part_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				series_id int(10) unsigned NOT NULL,
				user_id int(10) unsigned NOT NULL,
				article_id int(10) unsigned NOT NULL,
				series_part int(10) unsigned NOT NULL DEFAULT '1',
				series_part_title varchar(100) NOT NULL,
				series_part_create_date int(10) unsigned NOT NULL DEFAULT '0',
				series_part_edit_date int(10) unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (series_part_id),
				KEY series_part (series_part),
				KEY user_id (user_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
		");

		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ams_series_watch (
				user_id int(10) unsigned NOT NULL,
				series_id int(10) unsigned NOT NULL,
				notify_on enum('','series_part') NOT NULL,
				send_alert tinyint(3) unsigned NOT NULL,
				send_email tinyint(3) unsigned NOT NULL,
				PRIMARY KEY (user_id,series_id),
				KEY series_id_notify_on (series_id,notify_on)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
		
		$this->query("
			ALTER TABLE xf_user
				ADD ams_series_count int(10) unsigned NOT NULL DEFAULT '0' AFTER ams_article_count,
				ADD INDEX ams_series_count (ams_series_count)
		");	

		$this->query("ALTER TABLE xf_nflj_ams_article ADD series_part_id int(10) unsigned NOT NULL DEFAULT '0' AFTER featured");
		$this->query("ALTER TABLE xf_nflj_ams_category ADD article_image_required tinyint(3) unsigned NOT NULL DEFAULT '0'");
	}
		
	// ################################ UPGRADE TO AMS 1.5.0 RC 2 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1050052Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_article	CHANGE publish_date_timezone publish_date_timezone varchar(50) NOT NULL DEFAULT 'Europe/London'");
		
		$this->query("UPDATE xf_nflj_ams_article SET publish_date_timezone = 'Europe/London' WHERE publish_date_timezone = ''");
	}
		
	// ################################ UPGRADE TO AMS 1.5.1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1050170Step1()
	{
		$this->query("
			ALTER TABLE xf_nflj_ams_article
				ADD INDEX category_author_rating (category_id,author_rating),
				ADD INDEX author_rating (author_rating),
				ADD INDEX publish_date (publish_date),
				ADD INDEX category_id_publish_date (category_id,publish_date),
				ADD INDEX user_id_publish_date (user_id,publish_date)
		");
		
		$this->query("ALTER TABLE xf_nflj_ams_rate_review ADD INDEX rate_review_date (rate_review_date)");
	}

	// ################################ UPGRADE TO AMS 1.6.0 Beta 1 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1060031Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_article CHANGE article_location article_location varchar(255) NOT NULL DEFAULT ''");
		
		$this->query("
			ALTER TABLE xf_nflj_ams_article
				ADD xfmg_media_ids varbinary(100) NOT NULL DEFAULT '' AFTER xfmg_album_id,
				ADD warning_id int(10) unsigned NOT NULL DEFAULT '0',
				ADD warning_message varchar(255) NOT NULL DEFAULT ''
		");
		
		$this->query("ALTER TABLE xf_nflj_ams_article_page ADD attach_count int(10) unsigned NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_nflj_ams_comment ADD attach_count int(10) unsigned NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_nflj_ams_rate_review ADD warning_message varchar(255) NOT NULL DEFAULT '' AFTER warning_id");
		
		$this->query("
			CREATE TABLE IF NOT EXISTS xf_nflj_ams_article_reply_ban (
				article_reply_ban_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				article_id int(10) unsigned NOT NULL,
				user_id int(10) unsigned NOT NULL,
				ban_date int(10) unsigned NOT NULL,
				expiry_date int(10) unsigned DEFAULT NULL,
				reason varchar(100) NOT NULL DEFAULT '',
				ban_user_id int(10) unsigned NOT NULL,
				PRIMARY KEY (article_reply_ban_id),
				UNIQUE KEY article_id_user_id (article_id,user_id),
				KEY expiry_date (expiry_date),
				KEY user_id (user_id)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8
		");
	}		

	// ################################ UPGRADE TO AMS 1.6.0 Beta 2 ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1060032Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_article ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_nflj_ams_comment ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
		$this->query("ALTER TABLE xf_nflj_ams_rate_review ADD ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0'");
	}		

	// ################################ UPGRADE TO AMS 1.6.2  ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1060270Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_rate_review ADD username varchar(50) NOT NULL AFTER user_id");
	}		

	// ################################ UPGRADE TO AMS 1.6.3  ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1060370Step1()
	{	
		$this->query("ALTER TABLE xf_nflj_ams_article ADD author_suggested_ids varbinary(100) NOT NULL DEFAULT '' AFTER author_rating");
	}	

	// ################################ UPGRADE TO AMS 1.6.5  ##################
	// note: this is just translated from the XF1 version roughly as is
	
	public function upgrade1060570Step1()
	{
		$this->query("ALTER TABLE xf_nflj_ams_custom_field ADD is_filter_link tinyint(3) unsigned NOT NULL DEFAULT '0' AFTER is_searchable");
	}

	// ################################ START OF XF2 VERSION OF AMS ##################
	
	// ################################ UPGRADE TO 2.0.0 Alpha 1 ##################	
	
	public function upgrade2000011Step1()
	{
		$sm = $this->schemaManager();
	
		$renameTables = [
			'xf_nflj_ams_article' => 'xf_xa_ams_article',
			'xf_nflj_ams_article_page' => 'xf_xa_ams_article_page',
			'xf_nflj_ams_article_read' => 'xf_xa_ams_article_read',
			'xf_nflj_ams_article_reply_ban' => 'xf_xa_ams_article_reply_ban',
			'xf_nflj_ams_article_view' => 'xf_xa_ams_article_view',
			'xf_nflj_ams_article_watch' => 'xf_xa_ams_article_watch',
			'xf_nflj_ams_author_watch' => 'xf_xa_ams_author_watch',
			'xf_nflj_ams_category' => 'xf_xa_ams_category',
			'xf_nflj_ams_category_prefix' => 'xf_xa_ams_category_prefix',
			'xf_nflj_ams_category_watch' => 'xf_xa_ams_category_watch',
			'xf_nflj_ams_comment' => 'xf_xa_ams_comment',
			'xf_nflj_ams_comment_reply' => 'xf_xa_ams_comment_reply',
			'xf_nflj_ams_custom_field' => 'xf_xa_ams_article_field',
			'xf_nflj_ams_custom_field_category' => 'xf_xa_ams_article_field_category', 
			'xf_nflj_ams_custom_field_value' => 'xf_xa_ams_article_field_value',
			'xf_nflj_ams_feed' => 'xf_xa_ams_feed',
			'xf_nflj_ams_feed_log' => 'xf_xa_ams_feed_log',		
			'xf_nflj_ams_prefix' => 'xf_xa_ams_article_prefix',
			'xf_nflj_ams_prefix_group' => 'xf_xa_ams_article_prefix_group',
			'xf_nflj_ams_rate_review' => 'xf_xa_ams_article_rating',
			'xf_nflj_ams_review_field' => 'xf_xa_ams_review_field',
			'xf_nflj_ams_review_field_category' => 'xf_xa_ams_review_field_category',
			'xf_nflj_ams_review_field_value' => 'xf_xa_ams_review_field_value',
			'xf_nflj_ams_series' => 'xf_xa_ams_series',
			'xf_nflj_ams_series_part' => 'xf_xa_ams_series_part',
			'xf_nflj_ams_series_watch' => 'xf_xa_ams_series_watch'
		];
		
		foreach ($renameTables AS $from => $to)
		{
			$sm->renameTable($from, $to);
		}
	}	
	
	public function upgrade2000011Step2()
	{
		$sm = $this->schemaManager();

		// lets knock out the xf_user tables stuff here... and get them out of the way!
	
		$sm->alterTable('xf_user', function(Alter $table)
		{
			$table->renameColumn('ams_article_count', 'xa_ams_article_count');
			$table->renameColumn('ams_series_count', 'xa_ams_series_count');
		});
	
		$this->schemaManager()->alterTable('xf_user_option', function(Alter $table)
		{
			$table->dropColumns('ams_unread_articles_count');
		});
	
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->addColumn('xa_ams_about_author', 'text')->nullable(true);
		});
	
		$this->query("UPDATE xf_user_profile SET xa_ams_about_author = ams_about_author WHERE ams_about_author IS NOT NULL");
	
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->dropColumns('ams_about_author');
		});
	}	
	
	public function upgrade2000011Step3()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->renameColumn('category_name', 'title');
			$table->renameColumn('category_description', 'description');
			$table->renameColumn('category_image', 'content_image');
			$table->renameColumn('category_content', 'content_message');
			$table->renameColumn('category_content_title', 'content_title');
			$table->renameColumn('last_article', 'last_article_date');
			$table->renameColumn('rate_review_system', 'allow_ratings');
			$table->renameColumn('review_required', 'require_review');
			$table->renameColumn('category_style_id', 'style_id');
			$table->renameColumn('category_breadcrumb', 'breadcrumb_data');			
			$table->renameColumn('original_source_enabled', 'allow_original_source');
			$table->renameColumn('original_source_required', 'require_original_source');
			$table->renameColumn('article_image_required', 'require_article_image');
			
			$table->addColumn('featured_count', 'smallint')->setDefault(0)->after('article_count');
			$table->addColumn('allow_location', 'tinyint')->setDefault(0)->after('min_tags');
			$table->addColumn('layout_type', 'varchar', 25)->setDefault('');
		});
		
		$sm->alterTable('xf_xa_ams_category', function(Alter $table)
		{		
			$table->dropColumns(['category_options', 'modular_layout_options', 'modular_home_limit', 'modular_cat_limit']);
		});		

		$this->query("UPDATE xf_xa_ams_category SET allow_ratings = 1 WHERE allow_ratings = 2");
	}
	
	public function upgrade2000011Step4()
	{
		$sm = $this->schemaManager();	
		
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->dropColumns(['description', 'article_open', 'featured', 'cover_image_cache', 'image_attach_count', 'file_attach_count']);			
		});
		
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->renameColumn('article_view_count', 'view_count');
			$table->renameColumn('article_page_count', 'page_count');
			$table->renameColumn('article_location', 'location');
			$table->renameColumn('custom_article_fields', 'custom_fields');
			$table->renameColumn('thread_id', 'discussion_thread_id');
		
			$table->changeColumn('likes', 'int')->setDefault(0);
			$table->changeColumn('original_source', 'mediumblob')->nullable(true);
			$table->changeColumn('meta_description')->type('varchar')->length(320);
		});
		
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('description', 'varchar', 256)->setDefault('')->after('title');
			$table->addColumn('last_comment_id', 'int')->setDefault(0)->after('last_comment_date');
			$table->addColumn('last_comment_user_id', 'int')->setDefault(0)->after('last_comment_id');
			$table->addColumn('last_comment_username', 'varchar', 50)->setDefault('')->after('last_comment_user_id');
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});
		
		$sm->alterTable('xf_xa_ams_article_page', function(Alter $table)
		{
			$table->renameColumn('article_page_id', 'page_id');
			$table->renameColumn('article_page_state', 'page_state');
			
			$table->addColumn('embed_metadata', 'blob')->nullable();
		});	
		
		$sm->alterTable('xf_xa_ams_article_watch', function(Alter $table)
		{
			$table->dropColumns('watch_key');
		});			
	}

	public function upgrade2000011Step5()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_ams_comment', function(Alter $table)
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
		
		$sm->createTable('xf_xa_ams_comment_read', function(Create $table)
		{
			$table->addColumn('comment_read_id', 'int')->autoIncrement();
			$table->addColumn('user_id', 'int');
			$table->addColumn('article_id', 'int');
			$table->addColumn('comment_read_date', 'int');
			$table->addUniqueKey(['user_id', 'article_id']);
			$table->addKey('article_id');
			$table->addKey('comment_read_date');
		});	
		
	}
			
	public function upgrade2000011Step6()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_xa_ams_article_rating', function(Alter $table)
		{
			$table->changeColumn('likes', 'int')->setDefault(0);
			$table->changeColumn('like_users', 'blob')->nullable(true);
			$table->changeColumn('custom_review_fields', 'mediumblob')->nullable(true);
		});
		
		$sm->alterTable('xf_xa_ams_article_rating', function(Alter $table)
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
			
			$table->addKey(['article_id', 'rating_date']);
			$table->addKey(['user_id']);
		
			$table->dropColumns('review_title');

			$table->dropIndexes(['unique_rating', 'article_id']);
		});		
	}
	
	public function upgrade2000011Step7()
	{
		$sm = $this->schemaManager();
	
		$sm->dropTable('xf_xa_ams_article_view');
	
		$sm->createTable('xf_xa_ams_article_view', function(Create $table)
		{
			$table->addColumn('article_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('article_id');
		});

		$sm->createTable('xf_xa_ams_article_feature', function(Create $table)
		{
			$table->addColumn('article_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('article_id');
			$table->addKey('feature_date');
		});		
	}
	
	public function upgrade2000011Step8()
	{
		$sm = $this->schemaManager();
			
		$sm->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->renameColumn('series_name', 'title');
			$table->renameColumn('series_description', 'description');
			$table->renameColumn('series_create_date', 'create_date');
			$table->renameColumn('series_edit_date', 'edit_date');
			$table->renameColumn('series_part_count', 'part_count');
			$table->renameColumn('last_series_part_date', 'last_part_date');
			$table->renameColumn('last_series_part', 'last_part_id');
			$table->renameColumn('last_series_part_title', 'last_part_title');
			$table->renameColumn('last_series_part_article_id', 'last_part_article_id');
				
			$table->addColumn('community_series', 'tinyint')->setDefault(0);
			$table->addColumn('icon_date', 'int')->setDefault(0);           
		});
	
		$sm->alterTable('xf_xa_ams_series_part', function(Alter $table)
		{
			$table->renameColumn('series_part_id', 'part_id');
			$table->renameColumn('series_part', 'display_order');
			$table->renameColumn('series_part_title', 'title');
			$table->renameColumn('series_part_create_date', 'create_date');
			$table->renameColumn('series_part_edit_date', 'edit_date');
		});
	
		$sm->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->dropColumns(['series_featured', 'series_display_order', 'series_parts_rating_count', 'series_parts_rating_avg']);
		});
		
		$sm->createTable('xf_xa_ams_series_feature', function(Create $table)
		{
			$table->addColumn('series_id', 'int');
			$table->addColumn('feature_date', 'int');
			$table->addPrimaryKey('series_id');
			$table->addKey('feature_date');
		});
	}	
	
	public function upgrade2000011Step9(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
		
		$perPage = 250;
		
		$db = $this->db();
		
		$commentReplyIds = $db->fetchAllColumn($db->limit(
			'
				SELECT comment_reply_id
				FROM xf_xa_ams_comment_reply
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
			FROM xf_xa_ams_comment_reply AS comment_reply
			INNER JOIN xf_xa_ams_comment as comment
				ON (comment_reply.comment_id = comment.comment_id)
			WHERE comment_reply.comment_reply_id IN (' . $db->quote($commentReplyIds) . ') 
		');

		$db->beginTransaction();
		
		foreach ($commentReplies AS $commentReply)
		{
			$quotedComment = $this->getQuoteWrapper($commentReply);
			$message = $quotedComment . $commentReply['message'];

			$this->db()->query("
				INSERT INTO xf_xa_ams_comment
					(article_id, user_id, username, comment_date, comment_state, message, likes, like_users, warning_id, warning_message, ip_id)
				VALUES
					(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			", array($commentReply['article_id'], $commentReply['user_id'], $commentReply['username'], $commentReply['comment_reply_date'], 
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
			. ', ams-comment: ' . $commentReply['comment_id']
			. ', member: ' . $commentReply['comment_user_id']
			. '"]'
			. $commentReply['comment_message']
			. "[/QUOTE]\n";
	}	

	public function upgrade2000011Step10()
	{
		$sm = $this->schemaManager();
		
		$sm->dropTable('xf_xa_ams_comment_reply');
		
		$sm->alterTable('xf_xa_ams_comment', function(Alter $table)
		{
			$table->dropColumns(['reply_count', 'first_reply_date', 'last_reply_date', 'latest_reply_ids']);
		});
	}	
	
	public function upgrade2000011Step11(array $stepParams)
	{
		$stepParams = array_replace([
			'position' => 0
		], $stepParams);
	
		$perPage = 250;
		$db = $this->db();
	
		$articleIds = $db->fetchAllColumn($db->limit(
			'
				SELECT article_id
				FROM xf_xa_ams_article
				WHERE article_id > ?
				ORDER BY article_id
			', $perPage
		), $stepParams['position']);
		if (!$articleIds)
		{
			return true;
		}
	
		$db->beginTransaction();
	
		foreach ($articleIds AS $articleId)
		{
			$count = $db->fetchOne('
				SELECT  COUNT(*)
				FROM xf_xa_ams_comment
				WHERE article_id = ?
				AND comment_state = \'visible\'
			', $articleId);
	
			$db->update('xf_xa_ams_article', ['comment_count' => intval($count)], 'article_id = ?', $articleId);
		}
	
		$db->commit();
	
		$stepParams['position'] = end($articleIds);
	
		return $stepParams;
	}	

	public function upgrade2000011Step12() 
	{
		$sm = $this->schemaManager();
		$db = $this->db();
	
		$sm->alterTable('xf_xa_ams_article_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
		});
	
		foreach ($db->fetchAllKeyed("SELECT * FROM xf_xa_ams_article_field", 'field_id') AS $fieldId => $field)
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
				$db->update('xf_xa_ams_article_field', $update, 'field_id = ?', $fieldId);
			}
		}
	
		$sm->alterTable('xf_xa_ams_article_field', function(Alter $table)
		{
			$table->addColumn('display_on_tab', 'tinyint')->setDefault(0)->after('display_on_list');
			$table->addColumn('display_on_tab_field_id', 'varchar', 25)->setDefault('')->after('display_on_tab');
			$table->addColumn('allow_use_field_user_group_ids', 'blob');
			$table->addColumn('allow_view_field_owner_in_user_group_ids', 'blob');
			
			$table->dropColumns(['match_regex', 'match_callback_class', 'match_callback_method']);
		});
		
		$sm->alterTable('xf_xa_ams_article_field_value', function(Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
		});		
	
		$sm->renameTable('xf_xa_ams_article_field_category', 'xf_xa_ams_category_field');
		
		$sm->alterTable('xf_xa_ams_category_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('category_id')->length(10)->unsigned();
		});
		
		$this->query("
			UPDATE xf_xa_ams_article_field
			SET field_type = 'stars'
			WHERE field_type = 'rating'
		");
		
		$this->query("
			UPDATE xf_xa_ams_article_field
			SET field_type = 'textbox'
			WHERE field_type = 'datepicker'
		");
	}

	public function upgrade2000011Step13() 
	{
		$sm = $this->schemaManager();
		$db = $this->db();
	
		$sm->alterTable('xf_xa_ams_review_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('field_type')->resetDefinition()->type('varbinary', 25)->setDefault('textbox');
			$table->changeColumn('match_type')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->addColumn('match_params', 'blob')->after('match_type');
		});
	
		foreach ($db->fetchAllKeyed("SELECT * FROM xf_xa_ams_review_field", 'field_id') AS $fieldId => $field)
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
				$db->update('xf_xa_ams_review_field', $update, 'field_id = ?', $fieldId);
			}
		}
	
		$sm->alterTable('xf_xa_ams_review_field', function(Alter $table)
		{
			$table->dropColumns(['display_in_block', 'match_regex', 'match_callback_class', 'match_callback_method']);
		});
		
		$sm->alterTable('xf_xa_ams_review_field_value', function(Alter $table)
		{
			$table->renameColumn('rate_review_id', 'rating_id');
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');

			$table->dropColumns('article_id');
			$table->dropIndexes('articleId_fieldId');
		});		
	
		$sm->renameTable('xf_xa_ams_review_field_category', 'xf_xa_ams_category_review_field');
	
		$sm->alterTable('xf_xa_ams_category_review_field', function (Alter $table)
		{
			$table->changeColumn('field_id')->resetDefinition()->type('varbinary', 25)->setDefault('none');
			$table->changeColumn('category_id')->length(10)->unsigned();
		});
		
		$this->query("
			UPDATE xf_xa_ams_review_field
			SET field_type = 'stars'
			WHERE field_type = 'rating'
		");
		
		$this->query("
			UPDATE xf_xa_ams_review_field
			SET field_type = 'textbox'
			WHERE field_type = 'datepicker'
		");
	}	
	
	public function upgrade2000011Step14()
	{
		$map = [
			'ams_prefix_group_*' => 'ams_article_prefix_group.*',
			'ams_prefix_*' => 'ams_article_prefix.*',
			'ams_custom_field_*_choice_*' => 'xa_ams_article_field_choice.$1_$2',
			'ams_custom_field_*_desc' => 'xa_ams_article_field_desc.*',
			'ams_custom_field_*' => 'xa_ams_article_field_title.*',
			'ams_review_field_*_choice_*' => 'xa_ams_review_field_choice.$1_$2',
			'ams_review_field_*_desc' => 'xa_ams_review_field_desc.*',
			'ams_review_field_*' => 'xa_ams_review_field_title.*',
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
	
	public function upgrade2000011Step15()
	{
		$db = $this->db();
	
		// update prefix CSS classes to the new name
		$prefixes = $db->fetchPairs("
			SELECT prefix_id, css_class
			FROM xf_xa_ams_article_prefix
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
				$db->update('xf_xa_ams_article_prefix',
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
			FROM xf_xa_ams_category_field
		");
		foreach ($entries AS $entry)
		{
			$fieldCache[$entry['category_id']][$entry['field_id']] = $entry['field_id'];
		}
	
		$db->beginTransaction();
	
		foreach ($fieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_xa_ams_category',
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
			FROM xf_xa_ams_category_review_field
		");
		foreach ($entries AS $entry)
		{
			$reviewfieldCache[$entry['category_id']][$entry['field_id']] = $entry['field_id'];
		}
		
		$db->beginTransaction();
		
		foreach ($reviewfieldCache AS $categoryId => $cache)
		{
			$db->update(
				'xf_xa_ams_category',
				['review_field_cache' => serialize($cache)],
				'category_id = ?',
				$categoryId
			);
		}
		
		$db->commit();		
	}
	
	public function upgrade2000011Step16()
	{
		$db = $this->db();
	
		$associations = $db->fetchAll("
			SELECT cp.*
			FROM xf_xa_ams_category_prefix AS cp
			INNER JOIN xf_xa_ams_article_prefix as p ON
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
				'xf_xa_ams_category',
				['prefix_cache' => serialize($prefixes)],
				'category_id = ?',
				$categoryId
			);
		}
	
		$db->commit();
	}

	public function upgrade2000011Step17(array $stepParams)
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
				'ams_article_page',
				'ams_review',
				'ams_comment_reply'
			]
		], $stepParams);			
		
		$db = $this->db();
		$startTime = microtime(true);
		$maxRunTime = $this->app->config('jobMaxRunTime');

		if (!$stepParams['content_type_tables'])
		{
			$columns = [];

			$oldType = 'ams_review';
			$oldLen = strlen($oldType);
			
			$newType = 'ams_rating';
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
				if ($contentType == 'ams_article_page')
				{
					$db->update($table, ['content_type' => 'ams_page'], 'content_type = ?', $contentType);
				}
				else if ($contentType == 'ams_review')
				{
					$db->update($table, ['content_type' => 'ams_rating'], 'content_type = ?', $contentType);
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
	
	public function upgrade2000010Step18()
	{
		$db = $this->db();
	
		$this->query("
			UPDATE xf_permission
			SET permission_group_id = 'xa_ams'
			WHERE permission_group_id = 'nfljams'
			AND addon_id = 'NFLJ_AMS'
		");
		
		$this->query("
			UPDATE xf_permission_entry
			SET permission_group_id = 'xa_ams'
			WHERE permission_group_id = 'nfljams'
		");
		
		$tablesToUpdate = [
			'xf_permission',
			'xf_permission_entry',
			'xf_permission_entry_content'
		];
		
		$permissionDeletes = [
			'viewCategory',
			'searchFields',
			'editThreadId',
			'articleReplyBan',
			'amsAttachmentCountLimit',
			'amsAttachmentMaxFileSize',
			'amsAttachmentMaxHeight',
			'amsAttachmentMaxWidth',
			'pageAttachCountLimit',
			'pageAttachMaxFileSize',
			'pageAttachMaxHeight',
			'pageAttachMaxWidth',
			'uploadPageAttach',
			'viewPageAttach',
			'manageArticlePagesAny',
			'bypassModQueueComment',
			'commentAttachCountLimit',
			'commentAttachMaxFileSize',
			'commentAttachMaxHeight',
			'commentAttachMaxWidth',
			'replyToComment',
			'bypassModQueueReview',
			'reviewAttachCountLimit',
			'reviewAttachMaxFileSize',
			'reviewAttachMaxHeight',
			'reviewAttachMaxWidth',
			'canReviewArticleAnon'
		];

		$permissionRenames = [
			'viewAMS' => 'view',
			'viewAttachment' => 'viewArticleAttach',
			'likeArticle' => 'like',
			'createArticle' => 'add',
			'createMaxArticles' => 'maxArticleCount',
			'bypassModQueueArticle' => 'submitWithoutApproval',
			'editArticleSelf' => 'editOwn',
			'deleteArticleSelf' => 'deleteOwn',
			'featureArticleSelf' => 'featureArticleOwn',
			'tagArticleSelf' => 'tagOwnArticle',
			'tagArticleAny' => 'tagAnyArticle',
			'lockUnlockCommentsSelf' => 'lockUnlockCommentsOwn',
			'createMaxSeries' => 'maxSeriesCount',
			'manageSeriesSelf' => 'manageSeriesOwn',
			'manageArticlePagesSelf' => 'addPageOwnArticle',
			'viewModeratedArticles' => 'viewModerated',
			'viewDeletedArticles' => 'viewDeleted',
			'editArticleAny' => 'editAny',
			'deleteArticleAny' => 'deleteAny',
			'hardDeleteArticleAny' => 'hardDeleteAny',
			'undeleteArticle' => 'undelete',
			'approveUnapproveArticle' => 'approveUnapprove',
			'reassignArticle' => 'reassign',
			'moveArticle' => 'move',
			'featureUnfeatureArticle' => 'featureUnfeature',
			'lockUnlockComments' => 'lockUnlockCommentsAny',
			'warnArticle' => 'warn',
			'featureUnfeatureSeries' => 'featureUnfeatureSeriesAny',
			'viewComment' => 'viewComments',
			'postComment' => 'addComment',
			'editCommentSelf' => 'editComment',
			'deleteCommentSelf' => 'deleteComment',
			'editCommentAny' => 'editAnyComment',
			'deleteCommentAny' => 'deleteAnyComment',
			'hardDeleteCommentAny' => 'hardDeleteAnyComment',
			'viewReview' => 'viewReviews',
			'canRateArticle' => 'rate',
			'canRateArticleSelf' => 'rateOwn',
			'editReviewSelf' => 'editReview',
			'deleteReviewSelf' => 'deleteReview',
			'replyToReview' => 'reviewReply',
			'viewModeratedUserReviews' => 'viewModeratedReviews',
			'viewDeletedUserReviews' => 'viewDeletedReviews',
			'hardDeleteReviewAny' => 'hardDeleteAnyReview',
			'editReviewAny' => 'editAnyReview',
			'deleteReviewAny' => 'deleteAnyReview'
		];
		
		foreach ($tablesToUpdate AS $table)
		{
			$this->query("
				DELETE FROM $table
				WHERE permission_id IN(" . $this->db()->quote($permissionDeletes) . ")
				AND permission_group_id = 'xa_ams'
			");
			
			foreach ($permissionRenames AS $old => $new)
			{
				$db->update($table, [
					'permission_id' => $new
				], 'permission_id = ? AND permission_group_id = ?', [$old, 'xa_ams']);
			}
		}
	}

	public function upgrade2000011Step19()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_feed_log', function(Alter $table)
		{
			$table->changeColumn('unique_id')->resetDefinition()->type('varbinary', 250)->setDefault('none');
		});
	}
	
	public function upgrade2000011Step20()
	{
		$this->query("UPDATE xf_xa_ams_article SET last_update = publish_date WHERE last_update = 0");
		$this->query("UPDATE xf_xa_ams_article SET edit_date = last_update WHERE edit_date = 0");		
		$this->query("UPDATE xf_thread SET discussion_type = 'ams_article' WHERE discussion_type = 'ams'");
	}		
	
	public function upgrade2000011Step21()
	{
		$this->insertNamedWidget('xa_ams_latest_comments');
		$this->insertNamedWidget('xa_ams_latest_reviews');
		$this->insertNamedWidget('xa_ams_articles_statistics');
	}
	
	
	// ################################ UPGRADE TO 2.0.0 Beta 3 ##################
	
	public function upgrade2000033Step1()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('content_term', 'varchar', 100)->setDefault('')->after('content_title');
		});
	}
	
	
	// ################################ UPGRADE TO 2.0.0 Beta 4 ##################
	
	public function upgrade2000034Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_article_rating', function(Alter $table)
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
	
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('has_poll', 'tinyint')->setDefault(0)->after('location');
		});
		
		$sm->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('allow_poll', 'tinyint')->setDefault(0)->after('allow_location');
		});
		
		// fixes an issue where last_edit_date was being set when the content was created 
		$this->query("UPDATE xf_xa_ams_article SET last_edit_date = 0 WHERE edit_count = 0");
		$this->query("UPDATE xf_xa_ams_article_page SET last_edit_date = 0 WHERE edit_count = 0");
		$this->query("UPDATE xf_xa_ams_article_rating SET last_edit_date = 0 WHERE edit_count = 0");
	}
	
	
	// ################################ UPGRADE TO 2.0.2 ##################
	
	public function upgrade2000270Step1()
	{
		$sm = $this->schemaManager();
		
		// Some TITLE lengths that should be 150 may be incorrectly set to 100, so lets force a change of 150 on all of them to make sure they are all set to the correct length of 150.
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
		});	

		$sm->alterTable('xf_xa_ams_article_page', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
			$table->changeColumn('nav_title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->changeColumn('last_article_title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
			$table->changeColumn('last_part_title')->length(150);
		});
		
		$sm->alterTable('xf_xa_ams_series_part', function(Alter $table)
		{
			$table->changeColumn('title')->length(150);
		});
		
		
		// alter the xa_ams_about_author field in the xf_user_profile table to make it NULLABLE (and default NULL).
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{		
			$table->changeColumn('xa_ams_about_author', 'text')->nullable(true);
		});
		
		// drop these xfmg fields as we no longer use them
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
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
			'XenAddons\AMS:ArticleItem', ['like_users', 'custom_fields', 'tags', 'original_source'], $position, $stepData
		);
	}
	
	public function upgrade2010031Step2(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson(
			'XenAddons\AMS:ArticleRating', ['like_users', 'custom_fields'], $position, $stepData);
	}
	
	public function upgrade2010031Step3(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson(
			'XenAddons\AMS:Category', ['field_cache', 'review_field_cache', 'prefix_cache', 'breadcrumb_data'], $position, $stepData
		);
	}
	
	public function upgrade2010031Step4(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$stepData = empty($stepParams[2]) ? [] : $stepParams[2];
	
		return $this->entityColumnsToJson('XenAddons\AMS:Comment', ['like_users'], $position, $stepData);
	}
	
	
	public function upgrade2010031Step5()
	{
		$this->migrateTableToReactions('xf_xa_ams_article');
	}
	
	public function upgrade2010031Step6()
	{
		$this->migrateTableToReactions('xf_xa_ams_article_rating');
	}
	
	public function upgrade2010031Step7()
	{
		$this->migrateTableToReactions('xf_xa_ams_comment');
	}
	
	public function upgrade2010031Step8()
	{
		$this->renameLikeAlertOptionsToReactions(['ams_article', 'ams_comment', 'ams_rating']);
	}
	
	public function upgrade2010031Step9()
	{
		$this->renameLikeAlertsToReactions(['ams_article', 'ams_comment', 'ams_rating']);
	}
	
	public function upgrade2010031Step10()
	{
		$this->renameLikePermissionsToReactions([
			'xa_ams' => true // global and content
		], 'like');

		$this->renameLikePermissionsToReactions([
			'xa_ams' => true // global and content
		], 'likeReview', 'reactReview');

		$this->renameLikePermissionsToReactions([
			'xa_ams' => true // global and content
		], 'likeComment', 'reactComment');

		$this->renameLikeStatsToReactions(['article']);
	}
	
	
	// ################################ UPGRADE TO 2.1.4 ##################
	
	public function upgrade2010470Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('ratings_open', 'tinyint')->setDefault(1)->after('comments_open');
		});
		
		$sm->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('article_list_order', 'varchar', 25)->setDefault('');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.7 ##################
	
	public function upgrade2010770Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_article_field', function(Alter $table)
		{
			$table->addColumn('editable_user_group_ids', 'blob');
		});
	
		$db = $this->db();
		$db->beginTransaction();
	
		$fields = $db->fetchAll("
			SELECT *
			FROM xf_xa_ams_article_field
		");
		foreach ($fields AS $field)
		{
			$update = '-1';
	
			$db->update('xf_xa_ams_article_field',
				['editable_user_group_ids' => $update],
				'field_id = ?',
				$field['field_id']
			);
		}
	
		$db->commit();
	
		// drop all of the AMS 1.x fields that are no longer being used (They were used for a bespoke custom field searching and filtering system)
		$sm->alterTable('xf_xa_ams_article_field', function(Alter $table)
		{
			$table->dropColumns(['is_searchable', 'is_filter_link', 'fs_description', 'allow_use_field_user_group_ids', 'allow_view_field_user_group_ids', 'allow_view_field_owner_in_user_group_ids']);
		});
	
	}
	
	public function upgrade2010770Step2()
	{
		$sm = $this->schemaManager();		
		
		$sm->alterTable('xf_xa_ams_review_field', function(Alter $table)
		{
			$table->addColumn('editable_user_group_ids', 'blob');
		});
	
		$db = $this->db();
		$db->beginTransaction();
	
		$fields = $db->fetchAll("
			SELECT *
			FROM xf_xa_ams_review_field
		");
		foreach ($fields AS $field)
		{
			$update = '-1';
	
			$db->update('xf_xa_ams_review_field',
				['editable_user_group_ids' => $update],
				'field_id = ?',
				$field['field_id']
			);
		}
	
		$db->commit();
	
		// drop all of the AMS 1.x fields that are no longer being used (They were used for a bespoke custom field searching and filtering system)
		$sm->alterTable('xf_xa_ams_review_field', function(Alter $table)
		{
			$table->dropColumns(['allow_view_field_user_group_ids']);
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.8 ##################
	
	public function upgrade2010870Step1()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_ams_category', function(Alter $table)
		{		
			$table->addColumn('default_tags', 'mediumblob')->after('min_tags');
		});
		
		$sm->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->addColumn('tags', 'mediumblob');
		});
	}

	
	// ################################ UPGRADE TO 2.1.9 ##################
	
	public function upgrade2010970Step1()
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->renameColumn('cover_image_header', 'cover_image_above_article');
		});
	}

	
	// ################################ UPGRADE TO 2.1.11 ##################
	
	public function upgrade2011170Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->changeColumn('article_state', 'enum')->values(['visible','moderated','deleted','awaiting','draft'])->setDefault('visible');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.12 ##################
	
	public function upgrade2011270Step1()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('last_feature_date', 'int')->setDefault(0)->after('last_update');
		});
		
		$sm->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->addColumn('last_feature_date', 'int')->setDefault(0)->after('edit_date');
		});
	}
	
	
	// ################################ UPGRADE TO 2.1.13 (five steps) ##################
	
	public function upgrade2011370Step1()
	{	
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->addColumn('xa_ams_author_name', 'varchar', 50)->setDefault('')->after('xa_ams_about_author');
		});
		
		$sm->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('overview_page_title', 'varchar', 150)->setDefault('')->after('page_count');
			$table->addColumn('overview_page_nav_title', 'varchar', 150)->setDefault('')->after('overview_page_title');
		});
		
		$sm->alterTable('xf_xa_ams_article_page', function(Alter $table)
		{
			$table->addColumn('user_id', 'int')->setDefault(0)->after('article_id');
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
		
		$articlePages = $db->fetchAll('
			SELECT page.page_id, page.article_id,
				article.article_id, article.user_id, article.username
			FROM xf_xa_ams_article_page as page
			INNER JOIN xf_xa_ams_article AS article ON
				(page.article_id = article.article_id)
		');
		
		$db->beginTransaction();
		
		foreach ($articlePages AS $articlePage)
		{
			$this->query("
				UPDATE xf_xa_ams_article_page
				SET user_id = ?, username = ?
				WHERE page_id = ?
			", [$articlePage['user_id'], $articlePage['username'], $articlePage['page_id']]);
		}
		
		$db->commit();
	}
	
	public function upgrade2011370Step3()
	{
		$sm = $this->schemaManager();
	
		$sm->alterTable('xf_xa_ams_article_page', function(Alter $table)
		{		
			$table->addKey('user_id');
			$table->addKey(['article_id', 'create_date']);
			$table->addKey(['article_id', 'display_order']);
			$table->addKey('create_date');
		});
	}
		
	public function upgrade2011370Step4()
	{
		$sm = $this->schemaManager();
		
		$sm->alterTable('xf_xa_ams_series', function(Alter $table)
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
			FROM xf_xa_ams_series as series
			INNER JOIN xf_user AS user ON
				(series.user_id = user.user_id)
		');
		
		$db->beginTransaction();
		
		foreach ($series AS $seriesItem)
		{
			$this->query("
				UPDATE xf_xa_ams_series
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
			'ams_article' => 'xf_xa_ams_article_prefix'
		]);
	
		$this->insertThreadType('ams_article', 'XenAddons\AMS:ArticleItem', 'XenAddons/AMS');
	}
	
	public function upgrade2020031Step2()
	{	
		$this->createTable('xf_xa_ams_article_contributor', function(Create $table)
		{
			$table->addColumn('article_id', 'int');
			$table->addColumn('user_id', 'int');
			$table->addColumn('is_co_author', 'tinyint')->setDefault(0);
			$table->addPrimaryKey(['article_id', 'user_id']);
			$table->addKey('user_id');
		});
	}
	
	public function upgrade2020031Step3()
	{	
		$this->alterTable('xf_xa_ams_article_field', function(Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});
		
		$this->alterTable('xf_xa_ams_review_field', function(Alter $table)
		{
			$table->addColumn('wrapper_template', 'text')->after('display_template');
		});
	}
	
	public function upgrade2020031Step4()
	{
		$this->alterTable('xf_xa_ams_article_rating', function(Alter $table)
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
		$this->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('contributor_user_ids', 'blob')->after('username');
		});
	}
	
	public function upgrade2020031Step6()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('review_voting', 'varchar', 25)->setDefault('')->after('allow_ratings');
			$table->addColumn('allow_contributors', 'tinyint')->setDefault(0)->after('allow_articles');
		});
	}


	// ################################ UPGRADE TO 2.2.4 ##################
	
	public function upgrade2020470Step1()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('thread_set_article_tags', 'tinyint')->setDefault(0)->after('thread_prefix_id');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.5 ##################
	
	public function upgrade2020570Step1()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('expand_category_nav', 'tinyint')->setDefault(0);
		});
	}

	
	// ################################ UPGRADE TO 2.2.11 ##################
	
	public function upgrade2021170Step1()
	{
		$this->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('sticky', 'tinyint')->setDefault(0)->after('article_state');	
		});
	}

	public function upgrade2021170Step2()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('display_articles_on_index', 'tinyint')->setDefault(1);
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.12 ##################	

	public function upgrade2021270Step1()
	{	
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_ams_comment_count', 'int')->setDefault(0)->after('xa_ams_series_count');
			$table->addKey('xa_ams_comment_count', 'ams_comment_count');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.17 ##################
	
	public function upgrade2021770Step1()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('description');
			$table->addColumn('allow_index', 'enum')->values(['allow', 'deny', 'criteria'])->setDefault('allow');
			$table->addColumn('index_criteria', 'blob');
		});
	}
	
	public function upgrade2021770Step2()
	{
		$this->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
		});
	
		$this->alterTable('xf_xa_ams_article_page', function(Alter $table)
		{	
			$table->addColumn('description', 'varchar', 256)->setDefault('')->after('message');		
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
		});
	}
	
	public function upgrade2021770Step3()
	{
		$this->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->addColumn('og_title', 'varchar', 100)->setDefault('')->after('title');
			$table->addColumn('meta_title', 'varchar', 100)->setDefault('')->after('og_title');
			$table->addColumn('meta_description', 'varchar', 320)->setDefault('')->after('description');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.18 ##################
	
	public function upgrade2021870Step1()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->changeColumn('content_image', 'varchar', 200);
		});
	
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->renameColumn('content_image', 'content_image_url');
		});
	}
	

	// ################################ UPGRADE TO 2.2.19 ##################
	
	public function upgrade2021970Step1()
	{
		$this->alterTable('xf_xa_ams_article', function(Alter $table)
		{		
			$table->addColumn('article_read_time', 'float', '')->setDefault(0)->after('article_state');
		});
	}
	
	public function upgrade2021970Step2()
	{	
		$this->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->renameColumn('part_count', 'article_count');
		});	
		
		$this->alterTable('xf_xa_ams_series', function(Alter $table)
		{
			$table->dropColumns(['last_part_title']);
		});
	}
	
	public function upgrade2021970Step3()
	{
		$this->alterTable('xf_xa_ams_series_part', function(Alter $table)
		{
			$table->renameColumn('part_id', 'series_part_id');
		});

		$this->alterTable('xf_xa_ams_series_part', function(Alter $table)
		{
			$table->dropColumns(['title']);
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.20 ##################
	
	public function upgrade2022070Step1()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('allow_self_join_contributors', 'tinyint')->setDefault(0)->after('allow_contributors');
			$table->addColumn('max_allowed_contributors', 'smallint')->setDefault(0)->after('allow_self_join_contributors');		
		});
		
		// Set a default for max_allowed_contributors if allow_contrinutors is set 
		$this->query("UPDATE xf_xa_ams_category SET max_allowed_contributors = 25 WHERE allow_contributors = 1");
	}	

	
	// ################################ UPGRADE TO 2.2.21 ##################	
			
	public function upgrade2022170Step1()
	{	
		$this->createTable('xf_xa_ams_article_prompt', function(Create $table)
		{
			$table->addColumn('prompt_id', 'int')->autoIncrement();
			$table->addColumn('prompt_group_id', 'int');
			$table->addColumn('display_order', 'int');
			$table->addColumn('materialized_order', 'int')->comment('Internally-set order, based on prompt_group.display_order, prompt.display_order');
			$table->addKey('materialized_order');
		});
	}
	
	public function upgrade2022170Step2()
	{	
		$this->createTable('xf_xa_ams_article_prompt_group', function(Create $table)
		{
			$table->addColumn('prompt_group_id', 'int')->autoIncrement();
			$table->addColumn('display_order', 'int');
		});
	}

	public function upgrade2022170Step3()
	{
		$this->createTable('xf_xa_ams_category_prompt', function(Create $table)
		{
			$table->addColumn('category_id', 'int');
			$table->addColumn('prompt_id', 'int');
			$table->addPrimaryKey(['category_id', 'prompt_id']);
		});
	}
	
	public function upgrade2022170Step4()
	{
		$this->alterTable('xf_xa_ams_article', function(Alter $table)
		{	
			$table->addColumn('os_url_check_date', 'int')->setDefault(0)->after('original_source');
			$table->addColumn('os_url_check_fail_count', 'int')->setDefault(0)->after('os_url_check_date');
			$table->addColumn('last_os_url_check_code', 'int')->setDefault(0)->after('os_url_check_fail_count');
			$table->addColumn('disable_os_url_check', 'tinyint')->setDefault(0)->after('last_os_url_check_code');
		});
	}
	
	public function upgrade2022170Step5()
	{
		$this->alterTable('xf_xa_ams_category', function (Alter $table)
		{
			$table->addColumn('prompt_cache', 'mediumblob')->after('require_prefix')->comment('JSON data from xf_xa_ams_category_prompt');
		});
	}	
	
	
	// ################################ UPGRADE TO 2.2.24 ##################
		
	public function upgrade2022470Step1()
	{	
		$this->alterTable('xf_xa_ams_category', function (Alter $table)
		{	
			$table->addColumn('map_options', 'mediumblob');
			$table->addColumn('display_location_on_list', 'tinyint')->setDefault(0);
			$table->addColumn('location_on_list_display_type', 'varchar', 50);
		});	
	}
	
	public function upgrade2022470Step2()
	{
		$this->alterTable('xf_xa_ams_article', function (Alter $table)
		{	
			$table->addColumn('location_data', 'mediumblob')->after('location');
		});
	}
	

	// ################################ UPGRADE TO 2.2.25 ##################
	
	public function upgrade2022570Step1()
	{			
		$this->alterTable('xf_xa_ams_article', function (Alter $table)
		{
			$table->dropColumns(['overview_page_nav_title']);
		});
		
		$this->alterTable('xf_xa_ams_article_page', function (Alter $table)
		{
			$table->dropColumns(['nav_title']);
		});
	}


	// ################################ UPGRADE TO 2.2.26 ##################
	
	public function upgrade2022670Step1()
	{
		$this->alterTable('xf_xa_ams_category', function (Alter $table)
		{
			$table->addColumn('require_location', 'tinyint')->setDefault(0)->after('allow_location');
		});
	}
	
	public function upgrade2022670Step2()
	{
		// Lets run this to make sure that everone has these indexes on their article table!
		$this->alterTable('xf_xa_ams_article', function (Alter $table)
		{
			$table->addKey(['category_id', 'publish_date'], 'category_publish_date');
			$table->addKey(['category_id', 'last_update'], 'category_last_update');
			$table->addKey(['category_id', 'rating_weighted'], 'category_rating_weighted');
			$table->addKey(['user_id', 'last_update']);
			$table->addKey('publish_date');
			$table->addKey('last_update');
			$table->addKey('rating_weighted');
			$table->addKey('discussion_thread_id');
			$table->addKey('prefix_id');
		});
		
		// Lets run this to make sure that everone has this index on their xa_user table!
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addKey('xa_ams_series_count', 'ams_series_count');
		});
	}

	
	// ################################ UPGRADE TO 2.2.27 #################	
			
	public function upgrade2022770Step1()
	{
		$this->alterTable('xf_xa_ams_article', function (Alter $table)
		{			
			$table->addColumn('cover_image_caption', 'varchar', 500)->setDefault('')->after('cover_image_id');
		});
	}
	
	public function upgrade2022770Step2()
	{
		$this->alterTable('xf_xa_ams_article_page', function (Alter $table)
		{
			$table->addColumn('cover_image_caption', 'varchar', 500)->setDefault('')->after('cover_image_id');
		});
	}
	
	
	// ################################ UPGRADE TO 2.2.32 #################	
	
	public function upgrade2023270Step1()
	{	
		$this->alterTable('xf_xa_ams_article', function (Alter $table)
		{
			$table->addColumn('article_open', 'tinyint')->setDefault(1)->after('tags');
		});
	}
	
	public function upgrade2023270Step2()
	{	
		$this->createTable('xf_xa_ams_series_view', function(Create $table)
		{
			$table->addColumn('series_id', 'int');
			$table->addColumn('total', 'int');
			$table->addPrimaryKey('series_id');
		});
		
		$this->alterTable('xf_xa_ams_series', function (Alter $table)
		{		
			$table->addColumn('view_count', 'int')->setDefault(0)->after('tags');
			$table->addColumn('watch_count', 'int')->setDefault(0)->after('view_count');
		});
	}

	public function upgrade2023270Step3()
	{
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->addColumn('xa_ams_review_count', 'int')->setDefault(0)->after('xa_ams_comment_count');
			$table->addKey('xa_ams_review_count', 'ams_review_count');
		});
	}
	
	public function upgrade2023270Step4()
	{
		$widgetOptions = [
			'limit' => 10,
			'style' => 'list-view',
			'require_cover_or_content_image' => true
		];
	
		$this->insertNamedWidget('xa_ams_whats_new_overview_lastest_articles', $widgetOptions);
	}
	

	// ################################ UPGRADE TO 2.3.0 Alpha 1 ##################
	
	public function upgrade2030011Step1()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaAmsAllowedFileExtensions\'
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
				WHERE option_id = \'xaAmsAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}
	
	public function upgrade2030011Step2()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaAmsCommentAllowedFileExtensions\'
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
				WHERE option_id = \'xaAmsCommentAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}

	public function upgrade2030011Step3()
	{
		$db = $this->db();
	
		$attachmentExtensions = $db->fetchOne('
			SELECT option_value
			FROM xf_option
			WHERE option_id = \'xaAmsReviewAllowedFileExtensions\'
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
				WHERE option_id = \'xaAmsReviewAllowedFileExtensions\'
			', $newAttachmentExtensions);
		}
	}	
	
	public function upgrade2030011Step4(): void
	{
		$tables = [
			'xf_xa_ams_article_view',
			'xf_xa_ams_series_view'
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
		$this->alterTable('xf_xa_ams_series', function (Alter $table)
		{
			$table->addColumn('icon_optimized', 'tinyint')->setDefault(0)->after('icon_date');
		});
	}
	
	
	// ################################ UPGRADE TO 2.3.0 Release Candidate 5 ##################
	
	public function upgrade2030055Step1()
	{	
		$this->insertNamedWidget('xa_ams_trending_articles');
	}

	
	// ################################ UPGRADE TO 2.3.4 ##################
	
	public function upgrade2030470Step1()
	{
		$this->alterTable('xf_xa_ams_article_rating', function (Alter $table)
		{
			$table->addColumn('title', 'varchar', 100)->setDefault('')->after('rating');
		});
	}	
	
	
	// ################################ UPGRADE TO 2.3.6 ##################
	
	public function upgrade2030670Step1()
	{
		$this->alterTable('xf_xa_ams_article', function(Alter $table)
		{
			$table->addColumn('disable_self_join_contributors', 'tinyint')->setDefault(0)->after('disable_os_url_check');
		});
	}
	

	// ################################ UPGRADE TO 2.3.7 ##################
	
	public function upgrade2030770Step1()
	{
		$this->alterTable('xf_xa_ams_category', function(Alter $table)
		{
			$table->addColumn('display_who_read_this_article', 'tinyint')->setDefault(1)->after('display_articles_on_index');
		});
	}
	
	public function upgrade2030770Step2()
	{
		$this->createTable('xf_xa_ams_os_url_check_log', function(Create $table)
		{
			$table->addColumn('os_url_check_log_id', 'int')->autoIncrement();
			$table->addColumn('article_id', 'int');
			$table->addColumn('os_url', 'text');
			$table->addColumn('os_url_check_date', 'int')->setDefault(0);
			$table->addColumn('os_url_check_code', 'int')->setDefault(0);
			$table->addColumn('os_url_check_result', 'enum')->values(['success', 'failure', 'moderated'])->setDefault('success');
			$table->addKey('article_id');
			$table->addKey('os_url_check_date');
			$table->addKey(['article_id', 'os_url_check_date']);
		});
	}
	
	
	// ################################ UPGRADE TO 2.3.8 ##################	
	
	public function upgrade2030870Step1()
	{
		$this->alterTable('xf_xa_ams_category', function (Alter $table)
		{
			$table->addColumn('auto_feature', 'tinyint')->setDefault(0)->after('display_who_read_this_article');
		});
	}
	
	public function upgrade2030870Step2()
	{
		$this->alterTable('xf_xa_ams_article', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade2030870Step3()
	{
		$this->alterTable('xf_xa_ams_series', function (Alter $table)
		{
			$table->addColumn('featured', 'tinyint')->setDefault(0)->after('tags');
		});
	}
	
	public function upgrade2030870Step4(): void
	{
		$db = $this->db();
	
		$featuredArticles = $db->fetchAllKeyed(
				'SELECT article.*, article_feature.*
			FROM xf_xa_ams_article AS article
			INNER JOIN xf_xa_ams_article_feature AS article_feature
				ON (article_feature.article_id = article.article_id)
			ORDER BY article.article_id ASC',
				'article_id'
		);
	
		$rows = [];
		foreach ($featuredArticles AS $articleId => $article)
		{
			$rows[] = [
				'content_type' => 'ams_article',
				'content_id' => $articleId,
				'content_container_id' => $article['category_id'],
				'content_user_id' => $article['user_id'],
				'content_username' => $article['username'],
				'content_date' => $article['publish_date'],
				'content_visible' => ($article['article_state'] === 'visible'),
				'feature_user_id' => $article['user_id'],
				'feature_date' => $article['feature_date'],
				'snippet' => '',
			];
		}
	
		if (!empty($rows))
		{
			$db->beginTransaction();
	
			$db->insertBulk('xf_featured_content', $rows);
			$db->update(
				'xf_xa_ams_article',
				['featured' => 1],
				'article_id IN (' . $db->quote(array_keys($featuredArticles)) . ')'
			);
	
			$db->commit();
		}
	}
	
	public function upgrade2030870Step5(): void
	{
		$db = $this->db();
	
		$featuredSeries = $db->fetchAllKeyed(
				'SELECT series.*, series_feature.*
			FROM xf_xa_ams_series AS series
			INNER JOIN xf_xa_ams_series_feature AS series_feature
				ON (series_feature.series_id = series.series_id)
			ORDER BY series.series_id ASC',
				'series_id'
		);
	
		$rows = [];
		foreach ($featuredSeries AS $seriesId => $series)
		{
			$rows[] = [
				'content_type' => 'ams_series',
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
				'xf_xa_ams_series',
				['featured' => 1],
				'series_id IN (' . $db->quote(array_keys($featuredSeries)) . ')'
			);
	
			$db->commit();
		}
	}
	
	public function upgrade2030870Step6(): void
	{
		$this->dropTable('xf_xa_ams_article_feature');
	
		$this->dropTable('xf_xa_ams_series_feature');
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
	
		if ($previousVersion && $previousVersion < 2000010)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeArticleEmbedMetadataRebuild',
				'XenAddons\AMS:AmsArticleEmbedMetadata',
				['types' => 'attachments'],
				false
			);
			
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeArticlePageEmbedMetadataRebuild',
				'XenAddons\AMS:AmsArticlePageEmbedMetadata',
				['types' => 'attachments'],
				false
			);
			
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeCommentEmbedMetadataRebuild',
				'XenAddons\AMS:AmsCommentEmbedMetadata',
				['types' => 'attachments'],
				false
			);
			
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeReviewEmbedMetadataRebuild',
				'XenAddons\AMS:AmsReviewEmbedMetadata',
				['types' => 'attachments'],
				false
			);
	
			/** @var \XF\Service\RebuildNestedSet $service */
			$service = \XF::service('XF:RebuildNestedSet', 'XenAddons\AMS:Category', [
				'parentField' => 'parent_category_id'
			]);
			$service->rebuildNestedSetInfo();
			
			$likeContentTypes = [
				'ams_article',
				'ams_comment',
				'ams_rating'
			];
			foreach ($likeContentTypes AS $contentType)
			{
				$this->app->jobManager()->enqueueUnique(
					'xa_amsUpgradeLikeIsCountedRebuild_' . $contentType,
					'XF:LikeIsCounted',
					['type' => $contentType],
					false
				);
			}
		}
		
		if ($previousVersion && $previousVersion < 2021270)
		{			
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeUserCountRebuild',
				'XenAddons\AMS:UserArticleCount',
				[],
				false
			);
		}
		
		if ($previousVersion && $previousVersion < 2021970)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeArticleItemRebuild',
				'XenAddons\AMS:ArticleItem',
				[],
				false
			);		
		}
		
		if ($previousVersion && $previousVersion < 2022070)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeSeriesPartRebuild',
				'XenAddons\AMS:SeriesPart',
				[],
				false
			);
		}
		
		if ($previousVersion && $previousVersion < 2023270)
		{
			$this->app->jobManager()->enqueueUnique(
				'xa_amsUpgradeUserCountRebuild',
				'XenAddons\AMS:UserArticleCount',
				[],
				false
			);
		}

		// the following runs after every upgrade
		\XF::repository('XenAddons\AMS:ArticlePrefix')->rebuildPrefixCache();
		\XF::repository('XenAddons\AMS:ArticleField')->rebuildFieldCache();
		\XF::repository('XenAddons\AMS:ReviewField')->rebuildFieldCache();

		$this->enqueuePostUpgradeCleanUp();
	}
	
	
	
	
	
	// ############################################ UNINSTALL STEPS #########################
	
	public function uninstallStep1()
	{
		foreach (array_keys($this->getTables()) AS $tableName)
		{
			$this->dropTable($tableName);
		}
	
		foreach ($this->getDefaultWidgetSetup() AS $widgetKey => $widgetFn)
		{
			$this->deleteWidget($widgetKey);
		}
	}
	
	public function uninstallStep2()
	{
		$this->alterTable('xf_user', function(Alter $table)
		{
			$table->dropColumns(['xa_ams_article_count', 'xa_ams_series_count', 'xa_ams_comment_count', 'xa_ams_review_count']);
		});
		
		$this->alterTable('xf_user_profile', function(Alter $table)
		{
			$table->dropColumns(['xa_ams_about_author', 'xa_ams_author_name']);
		});
	}
	
	// TODO should probably update associted threads removing the AMS association (discussion_type = ams_article). 
	
	public function uninstallStep3()
	{
		$db = $this->db();
	
		$contentTypes = [
			'ams_article',
			'ams_category', 
			'ams_comment',
			'ams_citation',
			'ams_page', 
			'ams_rating', 
			'ams_reference',
			'ams_series', 
			'ams_series_part'
		];
	
		$this->uninstallContentTypeData($contentTypes);
		
		$db->beginTransaction();
	
		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'articleManagementSystem'");
		$db->delete('xf_permission_cache_content', "content_type = 'ams_category'");
		$db->delete('xf_permission_entry', "permission_group_id = 'xa_ams'");
		$db->delete('xf_permission_entry_content', "permission_group_id = 'xa_ams'");
	
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
			'xa_ams_trending_articles' => function ($key, array $options = [])
			{
				$options = array_replace([
					'contextual' => true,
					'style' => 'simple',
					'content_type' => 'ams_article',
				], $options);
			
				$this->createWidget(
					$key,
					'trending_content',
					[
						'positions' => [
							'xa_ams_index_sidenav' => 150,
							'xa_ams_category_sidenav' => 150,
						],
						'options' => $options,
					],
					'Trending articles'
				);
			},		
			'xa_ams_latest_comments' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
			
				$this->createWidget(
					$key,
					'xa_ams_latest_comments',
					[
						'positions' => [
							'xa_ams_index_sidenav' => 300,
							'xa_ams_category_sidenav' => 300
						],
						'options' => $options
					]
				);
			},
			'xa_ams_latest_reviews' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
		
				$this->createWidget(
					$key,
					'xa_ams_latest_reviews',
					[
						'positions' => [
							'xa_ams_index_sidenav' => 400,
							'xa_ams_category_sidenav' => 400
						],
						'options' => $options
					]
				);
			},
			'xa_ams_articles_statistics' => function($key, array $options = [])
			{
				$options = array_replace([], $options);
					
				$this->createWidget(
					$key,
					'xa_ams_articles_statistics',
					[
						'positions' => ['xa_ams_index_sidenav' => 1000],
						'options' => $options
					]
				);
			},
			'xa_ams_whats_new_overview_lastest_articles' => function($key, array $options = [])
			{
				$options = array_replace([
					'limit' => 10,
					'style' => 'list-view',
					'require_cover_or_content_image' => true
				], $options);
			
				$this->createWidget(
					$key,
					'xa_ams_latest_articles',
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
			// XenAddons\AMS: Article permissions
			$this->applyGlobalPermission('xa_ams', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewFull', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewArticleAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'react', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'add', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ams', 'uploadArticleAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ams', 'editOwn', 'forum', 'editOwnPost');
			
			$this->applyGlobalPermission('xa_ams', 'viewComments', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewCommentAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'reactComment', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'addComment', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ams', 'uploadCommentAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ams', 'editComment', 'forum', 'editOwnPost');
			
			$this->applyGlobalPermission('xa_ams', 'viewReviews', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewReviewAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'reactReview', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'rate', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'uploadReviewAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ams', 'editReview', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ams', 'reviewReply', 'forum', 'editOwnPost');
	
			$applied = true;
		}
	
		if (!$previousVersion || $previousVersion < 2000011)
		{
			// XenAddons\AMS: Article permissions
			$this->applyGlobalPermission('xa_ams', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewFull', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewArticleAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'react', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'add', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ams', 'uploadArticleAttach', 'forum', 'postThread');
			$this->applyGlobalPermission('xa_ams', 'editOwn', 'forum', 'editOwnPost');
				
			$this->applyGlobalPermission('xa_ams', 'viewComments', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewCommentAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'reactComment', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'addComment', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ams', 'uploadCommentAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ams', 'editComment', 'forum', 'editOwnPost');
				
			$this->applyGlobalPermission('xa_ams', 'viewReviews', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'viewReviewAttach', 'general', 'viewNode');
			$this->applyGlobalPermission('xa_ams', 'reactReview', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'rate', 'forum', 'react');
			$this->applyGlobalPermission('xa_ams', 'uploadReviewAttach', 'forum', 'postReply');
			$this->applyGlobalPermission('xa_ams', 'editReview', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('xa_ams', 'reviewReply', 'forum', 'editOwnPost');
			
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ams', 'inlineMod', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ams'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny', 'featureUnfeature')
			");
			
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ams', 'inlineMod', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ams'
					AND permission_id IN ('deleteAny', 'undelete', 'approveUnapprove', 'reassign', 'editAny', 'featureUnfeature')
			");
			
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ams', 'inlineModComment', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ams'
					AND permission_id IN ('deleteAnyComment', 'undeleteComment', 'approveUnapproveComment', 'editAnyComment')
			");
				
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ams', 'inlineModComment', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ams'
					AND permission_id IN ('deleteAnyComment', 'undeleteComment', 'approveUnapproveComment', 'editAnyComment')
			");
			
			$this->query("
				REPLACE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, 'xa_ams', 'inlineModReview', 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = 'xa_ams'
					AND permission_id IN ('deleteAnyComment', 'undeleteComment', 'approveUnapproveComment', 'editAnyComment')
			");
			
			$this->query("
				REPLACE INTO xf_permission_entry_content
					(content_type, content_id, user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT content_type, content_id, user_group_id, user_id, 'xa_ams', 'inlineModReview', 'content_allow', 0
				FROM xf_permission_entry_content
				WHERE permission_group_id = 'xa_ams'
					AND permission_id IN ('deleteAnyReview', 'undeleteReview', 'approveUnapproveReview', 'editAnyReview')
			");
	
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2020010)
		{
			$this->applyGlobalPermission('xa_ams', 'contentVote', 'xa_ams', 'rate');
			$this->applyContentPermission('xa_ams', 'contentVote', 'xa_ams', 'rate');
			$this->applyGlobalPermission('xa_ams', 'manageOwnContributors', 'xa_ams', 'editOwn');
			$this->applyContentPermission('xa_ams', 'manageOwnContributors', 'xa_ams', 'editOwn');
			$this->applyGlobalPermission('xa_ams', 'manageAnyContributors', 'xa_ams', 'editAny');
			$this->applyContentPermission('xa_ams', 'manageAnyContributors', 'xa_ams', 'editAny');
		
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2020870)
		{
			// XenAddons\AMS: Article permissions
			$this->applyGlobalPermission('xa_ams', 'viewArticleMap', 'xa_ams', 'view');
			$this->applyContentPermission('xa_ams', 'viewArticleMap', 'xa_ams', 'view');
		
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2022470)
		{
			// XenAddons\AMS: Article permissions
			$this->applyGlobalPermission('xa_ams', 'viewCategoryMap', 'xa_ams', 'viewArticleMap');
			$this->applyContentPermission('xa_ams', 'viewCategoryMap', 'xa_ams', 'viewArticleMap');
		
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2022870)
		{
			// XenAddons\AMS: Article permissions
			$this->applyGlobalPermission('xa_ams', 'manageSeoOptions', 'forum', 'postThread');
		
			$applied = true;
		}
		
		if (!$previousVersion || $previousVersion < 2030770)
		{
			// XenAddons\AMS: Article permissions
			$this->applyGlobalPermission('xa_ams', 'viewWhoReadArticle', 'xa_ams', 'viewFull');
			$this->applyGlobalPermission('xa_ams', 'viewWhoReadArticleOwn', 'xa_ams', 'viewFull');
			
			$applied = true;
		}
	
		return $applied;
	}	
}