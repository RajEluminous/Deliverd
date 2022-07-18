<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
Route::get('/showqr','ShowqrController@index');

Route::get('qr-code', function () 
{	ob_start();
	//$file = base_path('qrfiles/qr.png');
	QRCode::url('http://google.com')->setSize(10)->png();    
	$imageString = base64_encode( ob_get_contents() );
	ob_end_clean();
	
	 $imageString;
	 echo '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAcIAAAHCAQMAAABG1lsGAAAABlBMVEX///8AAABVwtN+AAAACXBIWXMAAA7EAAAOxAGVKw4bAAACfElEQVR4nO2aQXKEMAwEVcUDeJK/zpN4wFY5i6WRbcxuck/PgQRMs5cpaSwwQwghhBBCZjX0MivntOKne63t4DogIVfST/bX+xnndq1tfsP71PxaWx1vhoScyctax7VW6uW0VDstb1u21WZQSMivZDituW/vBS3cBwn5OzmVMZuaIiTkVzLXsoypAf65f0L+bzKkZvd80F2QkCt5U/Oc6/Jh2PLz/ZCQ4b7wl5x26Bm+wWtlrHjOgoRcyGvNL4/xu4653H8BEvIDGe5rW7iYLHl0agOBwZsZ0yEh164Wybuq7fn9pvjdbFkgIb+SaoBmplpW1AW136uQkE+knBYdLyx4LXgDzIiVC5CQd/KM6BStsN2V7vMgXqsfbu6DhAyy/d3qAHXPHcNUYEnjkJCdLFXGa60wTqW0JSTkI1lvEwBNBYpKm65BQj6S3XiBWzoyX7d5TF9yPCTkQLr2KpP5KEmRvOhzEjNIyJW8lP1Otcw0EChK41OmgoScMHW3Gpv/cF8feMe44Lz/HiSk35/zx5hJVqVxy+JVNFmapwKQkE7WHpis/9fe89t9KgAJ+UT62h7F6/YyJDy3Z2mDhFxJ731yn83buj4peIxfkJCWdav2IL7pG6TWBdUU6/q9CSSkSG3cciqgWYCe6w8fDAoJOZHDi9qcZl/KcB6t0CAhH8nxGZa1bO9buN4Un6aTkJAmc2XdymDlBy08dEFIyO65cUQ5h6jBlqfNgoQUqS6oLVyt/UXt0AWf5gmQkDN5+JXI5SpZW0CttB2QkN/JvPXUhLtDFRLyE9n+KH73r0bGDV7/iAQSciVDr9yzbTmnNBugJkjIlUQIIYQQ+r/6Aa1N7UyFt2PgAAAAAElFTkSuQmCC" />';
	/* //return QRCode::text('QR Code Generator for Laravel!')->setOutfile($file)->png();    
	
	echo "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />";
	
		ob_start();
	//$file = base_path('qrfiles/qr.png');
	QRCode::url('http://amazon.com')->setSize(10)->png();    
	$imageString1 = base64_encode( ob_get_contents() );
	ob_end_clean();
	 echo '<img src="data:image/png;base64,'.$imageString1.'" />'; */
});