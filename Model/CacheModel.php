<?php


class CacheModel extends AppModel
{

	public $actsAs = array( 'Cache' );

	public function query( $sql, $param = array(), $cache = false )
	{

		if( $this->enabled() )
		{
			return $this->cacheMethod( __FUNCTION__, func_get_args() );	
		}

		return parent::query( $sql, $param, $cache );
	}

	public function find( $type = 'first', $params = array() )
	{
		if( $this->enabled() )
		{
			return $this->cacheMethod( __FUNCTION__, func_get_args() );
		}

		return parent::find( $type, $params );
	}
}
