public function __construct()
{
    $this->middleware('auth');
    $this->middleware('role:admin');
}