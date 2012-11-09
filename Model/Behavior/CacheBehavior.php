<?php


class CacheBehavior extends ModelBehavior
{

	private $cache_enabled = false;
	private $stop_recursive = false;
	
	private $_defaultConfig = 'default';

	public function setup( Model $model, $settings = array() )
	{

		if( Configure::read( 'Cache.disable' ) != true )
		{
			$this->cache_enabled = true;
		}

		if( Configure::read( 'CacheBehavior.enable' ) != true )
		{
			$this->cache_enabled = false;
		}

	}

	public function cacheMethod( Model $model, $method, $args = array() )
	{

		if( $this->stop_recursive )
		{
			throw new Exception( '再帰的にcacheMethodが呼び出されています' );
		}

		// キーを生成
		$key_name = $this->createCacheKey( $model, $method, $args );
		// キャッシュから読み込み
		$cache_retval = Cache::read( $key_name, $this->getConfig( $model ) );

		$value_exists = !empty( $cache_retval );

		if( $value_exists )
		{
			return $cache_retval;
		}
		else
		{
			// 再帰呼び出し時に呼び出されるのをストップ
			$this->stop_recursive = true;

			$func_retval = call_user_func_array( array( $model, $method ), $args );

			$this->stop_recursive = false;

			// キャッシュへ書き込み
			Cache::write( $key_name, $func_retval, $this->getConfig( $model ) );

			// キャッシュ削除用のリストを生成
			$list_key_name = $this->createCacheListKey( $model );
			$list = Cache::read( $list_key_name , $this->getConfig( $model ) );

			$list[] = $key_name;
			Cache::write( $list_key_name, $list , $this->getConfig( $model ) );

			return $func_retval;
		}
	}

	/**
	 * キャシュ保存用のキーを生成
	 */
	private function createCacheKey( Model $model, $method, $args = array() )
	{
		$param_list = array(
			get_class( $model ),
			$method,
			md5( serialize( $args ) ),
		);

		return join( '_', $param_list );
	}

	/**
	 * 保存しているキーリストの保存用キー
	 */
	private function createCacheListKey( Model $model )
	{
		$param_list = array(
			get_class( $model ),
			'cacheMehodList',
		);
		return join( '_', $param_list );
	}

	/**
	 * このBehaviorが使えるかどうか
	 * Cache自体が使えない場合、もしくは再帰的に呼び出されている状態の場合にfalseを返す
	 */
	public function enabled( Model $model )
	{
		return $this->cache_enabled && !( $this->stop_recursive );
	}

	/**
	 * データベース上のデータが更新されたら、キャッシュ上のデータを消す
	 */
	public function afterSave( Model $model, $created )
	{
		$this->deleteCacheAll( $model );
	}
	public function afterDelete( Model $model )
	{
		$this->deleteCacheAll( $model );
	}

	/**
	 * キャッシュ上のデータを削除する
	 */
	public function deleteCacheAll( Model $model )
	{
		$list_key = $this->createCacheListKey( $model );

		$list = Cache::read( $list_key, $this->getConfig( $model ) );

		if( empty( $list ) ) return;

		foreach( $list as $value )
		{
			Cache::delete( $value, $this->getConfig( $model ) );
		}
		Cache::delete( $list_key, $this->getConfig( $model ) );
	}

	public function getConfig( Model $model )
	{
		if( property_exists( $model, 'cacheConfig' ) )
		{
			return $model->cacheConfig;
		}
		else
		{
			return $this->_defaultConfig;
		}
	}
}
