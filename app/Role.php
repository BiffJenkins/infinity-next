<?php namespace App;

use App\Permission;
use App\Contracts\PermissionUser;
use Illuminate\Database\Eloquent\Model;

class Role extends Model {
	
	/**
	 * These constants represent the hard ID top-level system roles.
	 */
	const ID_ANONYMOUS     = 1;
	const ID_ADMIN         = 2;
	const ID_MODERATOR     = 3;
	const ID_OWNER         = 4;
	const ID_JANITOR       = 5;
	const ID_UNACCOUNTABLE = 6;
	const ID_REGISTERED    = 7;
	const ID_ABSOLUTE      = 8;
	
	/**
	 * These constants represent the weights of hard ID top-level system roles.
	 */
	const WEIGHT_ANONYMOUS     = 0;
	const WEIGHT_ADMIN         = 100;
	const WEIGHT_MODERATOR     = 80;
	const WEIGHT_OWNER         = 60;
	const WEIGHT_JANITOR       = 40;
	const WEIGHT_UNACCOUNTABLE = 20;
	const WEIGHT_REGISTERED    = 30;
	const WEIGHT_ABSOLUTE      = 1000;
	
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'roles';
	
	/**
	 * The table's primary key.
	 *
	 * @var string
	 */
	protected $primaryKey = 'role_id';
	
	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		// These three together must be unique. (role,board_uri,caste)
		'role',       // The group name. Very loosely ties masks together.
		'board_uri',  // The board URI. Can be NULL to affect all boards.
		'caste',      // An internal name to separate roles into smaller groups.
		
		'name',       // Internal nickname. Passes through translator, so language tokens work.
		'capcode',    // Same as above, but can be null. If null, it provides no capcode when posting.
		
		'inherit_id', // PK for another Role that this directly inherits permissions from.
		'system',     // Boolean. If TRUE, it indicates the mask is a very important system role that should not be deleted.
		'weight',     // Determines the order of permissions when compiled into a mask.
	];
	
	/**
	 * Indicates their is no autoupdated timetsamps.
	 *
	 * @var boolean
	 */
	public $timestamps = false;
	
	
	public function board()
	{
		return $this->belongsTo('\App\Board', 'board_id');
	}
	
	public function inherits()
	{
		return $this->hasOne('\App\Role', 'role_id', 'inherit_id');
	}
	
	public function permissions()
	{
		return $this->belongsToMany("\App\Permission", 'role_permissions', 'role_id', 'permission_id')->withPivot('value');
	}
	
	public function users()
	{
		return $this->belongsToMany('\App\User', 'user_roles', 'role_id', 'user_id');
	}
	
	/**
	 * Returns a human-readable name for this role.
	 *
	 * @return string 
	 */
	public function getDisplayName()
	{
		return trans_choice($this->name, is_null($this->board_uri) ? 0 : 1, [
			'role'      => $this->role,
			'board_uri' => $this->board_uri,
			'caste'     => $this->caste,
		]);
	}
	
	/**
	 * Returns a human-readable name for this role.
	 *
	 * @return string 
	 */
	public function getDisplayWeight()
	{
		return "{$this->weight} kg";
	}
	
	/**
	 * Returns owner role (found or created) for a specific board.
	 *
	 * @param  \App\Board  $board
	 * @return \App\Role
	 */
	public static function getOwnerRoleForBoard(Board $board)
	{
		return static::firstOrCreate([
			'role'       => "owner",
			'board_uri'  => $board->board_uri,
			'caste'      => NULL,
			'inherit_id' => Role::ID_OWNER,
			'name'       => "user.role.owner",
			'capcode'    => "user.role.owner",
			'system'     => false,
			'weight'     => Role::WEIGHT_OWNER + 5,
		]);
	}
	
	/**
	 * Returns the individual value for a requested permission.
	 *
	 * @param  \App\Permission  $permission
	 * @return boolean|null
	 */
	public function getPermission(Permission $permission)
	{
		foreach ($this->permissions as $thisPermission)
		{
			if ($thisPermission->permission_id == $permission->permission_id)
			{
				return !!$thisPermission->pivot->value;
			}
		}
		
		return null;
	}
	
	/**
	 *
	 *
	 */
	public function getPermissionsURL()
	{
		return url("/cp/roles/permissions/{$this->role_id}");
	}
	
	/**
	 * Builds a single role mask for all boards, called by name.
	 *
	 * @param  array|Collection  $roleMasks
	 * @return array
	 */
	public static function getRoleMaskByName($roleMasks)
	{
		$roles = static::whereIn('role', (array) $roleMasks)
			->orWhere('role_id', static::ID_ANONYMOUS)
			->with('permissions')
			->get();
		
		return static::getRolePermissions($roles);
	}
	
	/**
	 * Builds a single role mask for all boards, called by id.
	 *
	 * @param  array|integer  $roleIDs  Role primary keys to compile together.
	 * @return array
	 */
	public static function getRoleMaskByID($roleIDs)
	{
		$roles = static::whereIn('role_id', (array) $roleIDs)
			->orWhere('role_id', static::ID_ANONYMOUS)
			->with('permissions')
			->get();
		
		return static::getRolePermissions($roles);
	}
	
	/**
	 * Narrows query to only roles which are for a board and can be manipulated by this user.
	 *
	 * @param  \App\Board  $board
	 * @param  \App\Contracts\PermissionUser $user
	 * @return  Query
	 */
	public function scopeWhereBoardRole($query, Board $board, PermissionUser $user)
	{
		return $query->where(function($query) use ($board, $user) {
			$weight = -1;
			
			if ($user->canEditConfig(null))
			{
				$weight = Role::WEIGHT_ADMIN;
			}
			else if ($user->canEditConfig($board))
			{
				$weight = Role::WEIGHT_OWNER;
			}
			
			
			$query->where('board_uri', $board->board_uri);
			$query->where('weight', '<', $weight);
		})
		->orderBy('weight', 'desc');
	}
	
	/**
	 * Narrows query to only roles which are, or inherit, a specific role_id.
	 *
	 * @param  int  $role_id
	 * @return Query
	 */
	public function scopeWhereLevel($query, $role_id)
	{
		return $query->where(function($query) use ($role_id) {
			$query->where('inherit_id', '=', $role_id);
			$query->orWhere('role_id', '=', $role_id);
		});
	}
}
