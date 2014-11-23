// 12:25 PM 9/05/2011


$(function()
{
	$("#currentdlstate").html( 'Downloading file...' );
	$.post('./ajax/kernel-fetcher.php', { msg: "dl" }, dlComplete, "text");
});
				
function dlComplete(data, textStatus) 
{
	if ( textStatus == "success" )
	{
		if ( data == "##OK##" )
		{
			$("#currentdlstate").html( 'Download Completed! Moving file to TFTP server...' );
			$.post('./ajax/kernel-fetcher.php', { msg: "tftp" }, mvComplete, "text");
		}
		else
		{
			$("#currentdlstate").html( "<div class='task-start-failed'>" + data + "</div>" );
			$("#img").fadeOut( 'slow' );
		}
	}
	else
	{
		$("#currentdlstate").html( '<div class="task-start-failed">Download Failed!</div>');
		$("#img").fadeOut( 'slow' );
	}
}

function mvComplete(data, textStatus) 
{
	if ( textStatus == "success" )
	{
		if ( data == "##OK##" )
		{
			$("#currentdlstate").html( "<div class='task-start-ok'>" + 'Your new FOG kernel has been installed!</div>'); 
		}
		else
		{
			$("#currentdlstate").html( "<div class='task-start-failed'>" + data + "</div>" );
		}
	}
	else
	{
		$("#currentdlstate").html( "<div class='task-start-failed'>"+ 'Failed to load new kernel to TFTP Server!</div>' );
	}
	$("#img").fadeOut( 'slow' );
}
