<?php
/**	Class Name: FOGConfigurationPage 
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This class controls the FOG Configuration Page of FOG.
	It, now, allows a place for users to configure FOG Settings,
	Services, Active Directory settings, Version infro, Kernel
	updates, PXE Menu, Service Client updates, MAC lists, and
	has an ssh viewer for actual terminal based management of the
	server.  These controls are globally to my understanding.

	Manages server settings..

	Useful for:
	Making configuration changes to the server, PXE, kernel, etc....
**/
class FOGConfigurationPage extends FOGPage
{
	// Base variables
	var $name = 'FOG Configuration';
	var $node = 'about';
	var $id = 'id';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	// Pages
	/** index()
		Displays the configuration page.  Right now it redirects to display
		whether the user is on the current version.
	*/
	public function index()
	{
		$this->version();
	}
	// Version
	/** version()
		Pulls the current version from the internet.
	*/
	public function version()
	{
		// Set title
		$this->title = _('FOG Version Information');
		print "\n\t\t\t<p>"._('Version: ').FOG_VERSION.'</p>';
		print "\n\t\t\t".'<p><div class="sub">'.$this->FOGCore->FetchURL("http://freeghost.sourceforge.net/version/index.php?version=".FOG_VERSION).'</div></p>';
	}
	// Licence
	/** license()
		Displays the GNU License to the user.  Currently Version 3.
	*/
	public function license()
	{
		// Set title
		$this->title = _('FOG License Information');
		print "\n\t\t\t<pre>
	GNU GENERAL PUBLIC LICENSE
		 
Version 3, 29 June 2007

Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>

Everyone is permitted to copy and distribute verbatim copies of this license document, but changing it is not 
allowed.

Preamble

The GNU General Public License is a free, copyleft license for software and other kinds of works.

The licenses for most software and other practical works are designed to take away your freedom to share and change the works. By contrast, the GNU General Public License is intended to guarantee your freedom to share and change all versions of a program--to make sure it remains free software for all its users. We, the Free Software Foundation, use the GNU General Public License for most of our software; it applies also to any other work released this way by its authors. You can apply it to your programs, too.

When we speak of free software, we are referring to freedom, not price. Our General Public Licenses are designed to make sure that you have the freedom to distribute copies of free software (and charge for them if you wish), that you receive source code or can get it if you want it, that you can change the software or use pieces of it in new free programs, and that you know you can do these things.

To protect your rights, we need to prevent others from denying you these rights or asking you to surrender the rights. Therefore, you have certain responsibilities if you distribute copies of the software, or if you modify it: responsibilities to respect the freedom of others.

For example, if you distribute copies of such a program, whether gratis or for a fee, you must pass on to the recipients the same freedoms that you received. You must make sure that they, too, receive or can get the source code. And you must show them these terms so they know their rights.

Developers that use the GNU GPL protect your rights with two steps: (1) assert copyright on the software, and (2) offer you this License giving you legal permission to copy, distribute and/or modify it.

For the developers' and authors' protection, the GPL clearly explains that there is no warranty for this free software. For both users' and authors' sake, the GPL requires that modified versions be marked as changed, so that their problems will not be attributed erroneously to authors of previous versions.

Some devices are designed to deny users access to install or run modified versions of the software inside them, although the manufacturer can do so. This is fundamentally incompatible with the aim of protecting users' freedom to change the software. The systematic pattern of such abuse occurs in the area of products for individuals to use, which is precisely where it is most unacceptable. Therefore, we have designed this version of the GPL to prohibit the practice for those products. If such problems arise substantially in other domains, we stand ready to extend this provision to those domains in future versions of the GPL, as needed to protect the freedom of users.

Finally, every program is threatened constantly by software patents. States should not allow patents to restrict development and use of software on general-purpose computers, but in those that do, we wish to avoid the special danger that patents applied to a free program could make it effectively proprietary. To prevent this, the GPL assures that patents cannot be used to render the program non-free.

The precise terms and conditions for copying, distribution and modification follow.

TERMS AND CONDITIONS
0. Definitions.

&#x201c;This License&#x201d; refers to version 3 of the GNU General Public License.

&#x201c;Copyright&#x201d; also means copyright-like laws that apply to other kinds of works, such as semiconductor masks.

&#x201c;The Program&#x201d; refers to any copyrightable work licensed under this License. Each licensee is addressed as &#x201c;you&#x201d;. &#x201c;Licensees&#x201d; and &#x201c;recipients&#x201d; may be individuals or organizations.

To &#x201c;modify&#x201d; a work means to copy from or adapt all or part of the work in a fashion requiring copyright permission, other than the making of an exact copy. The resulting work is called a &#x201c;modified version&#x201d; of the earlier work or a work &#x201c;based on&#x201d; the earlier work.

A &#x201c;covered work&#x201d; means either the unmodified Program or a work based on the Program.

To &#x201c;propagate&#x201d; a work means to do anything with it that, without permission, would make you directly or secondarily liable for infringement under applicable copyright law, except executing it on a computer or modifying a private copy. Propagation includes copying, distribution (with or without modification), making available to the public, and in some countries other activities as well.

To &#x201c;convey&#x201d; a work means any kind of propagation that enables other parties to make or receive copies. Mere interaction with a user through a computer network, with no transfer of a copy, is not conveying.

An interactive user interface displays &#x201c;Appropriate Legal Notices&#x201d; to the extent that it includes a convenient and prominently visible feature that (1) displays an appropriate copyright notice, and (2) tells the user that there is no warranty for the work (except to the extent that warranties are provided), that licensees may convey the work under this License, and how to view a copy of this License. If the interface presents a list of user commands or options, such as a menu, a prominent item in the list meets this criterion.

1. Source Code.

The &#x201c;source code&#x201d; for a work means the preferred form of the work for making modifications to it. &#x201c;Object code&#x201d; 
means any non-source form of a work.

A &#x201c;Standard Interface&#x201d; means an interface that either is an official standard defined by a recognized standards body, or, in the case of interfaces specified for a particular programming language, one that is widely used among developers working in that language.

The &#x201c;System Libraries&#x201d; of an executable work include anything, other than the work as a whole, that (a) is included in the normal form of packaging a Major Component, but which is not part of that Major Component, and (b) serves only to enable use of the work with that Major Component, or to implement a Standard Interface for which an implementation is available to the public in source code form. A &#x201c;Major Component&#x201d;, in this context, means a major essential component (kernel, window system, and so on) of the specific operating system (if any) on which the executable work runs, or a compiler used to produce the work, or an object code interpreter used to run it.

The &#x201c;Corresponding Source&#x201d; for a work in object code form means all the source code needed to generate, install, and (for an executable work) run the object code and to modify the work, including scripts to control those activities. However, it does not include the work's System Libraries, or general-purpose tools or generally available free programs which are used unmodified in performing those activities but which are not part of the work. For example, Corresponding Source includes interface definition files associated with source files for the work, and the source code for shared libraries and dynamically linked subprograms that the work is specifically designed to require, such as by intimate data communication or control flow between those subprograms and other parts of the work.

The Corresponding Source need not include anything that users can regenerate automatically from other parts of the Corresponding Source.

The Corresponding Source for a work in source code form is that same work.

2. Basic Permissions.

All rights granted under this License are granted for the term of copyright on the Program, and are irrevocable provided the stated conditions are met. This License explicitly affirms your unlimited permission to run the unmodified Program. The output from running a covered work is covered by this License only if the output, given its content, constitutes a covered work. This License acknowledges your rights of fair use or other equivalent, as provided by copyright law.

You may make, run and propagate covered works that you do not convey, without conditions so long as your license otherwise remains in force. You may convey covered works to others for the sole purpose of having them make modifications exclusively for you, or provide you with facilities for running those works, provided that you comply with the terms of this License in conveying all material for which you do not control copyright. Those thus making or running the covered works for you must do so exclusively on your behalf, under your direction and control, on terms that prohibit them from making any copies of your copyrighted material outside their relationship with you.

Conveying under any other circumstances is permitted solely under the conditions stated below. Sublicensing is not allowed; section 10 makes it unnecessary.

3. Protecting Users' Legal Rights From Anti-Circumvention Law.

No covered work shall be deemed part of an effective technological measure under any applicable law fulfilling obligations under article 11 of the WIPO copyright treaty adopted on 20 December 1996, or similar laws prohibiting or restricting circumvention of such measures.

When you convey a covered work, you waive any legal power to forbid circumvention of technological measures to the extent such circumvention is effected by exercising rights under this License with respect to the covered work, and you disclaim any intention to limit operation or modification of the work as a means of enforcing, against the work's users, your or third parties' legal rights to forbid circumvention of technological measures.

4. Conveying Verbatim Copies.

You may convey verbatim copies of the Program's source code as you receive it, in any medium, provided that you conspicuously and appropriately publish on each copy an appropriate copyright notice; keep intact all notices stating that this License and any non-permissive terms added in accord with section 7 apply to the code; keep intact all notices of the absence of any warranty; and give all recipients a copy of this License along with the Program.

You may charge any price or no price for each copy that you convey, and you may offer support or warranty protection for a fee.

5. Conveying Modified Source Versions.

You may convey a work based on the Program, or the modifications to produce it from the Program, in the form of source code under the terms of section 4, provided that you also meet all of these conditions:

 * a) The work must carry prominent notices stating that you modified it, and giving a relevant date.
 * b) The work must carry prominent notices stating that it is released under this License and any conditions added under section 7. This requirement modifies the requirement in section 4 to &#x201c;keep intact all notices&#x201d;.
 * c) You must license the entire work, as a whole, under this License to anyone who comes into possession of a copy. This License will therefore apply, along with any applicable section 7 additional terms, to the whole of the work, and all its parts, regardless of how they are packaged. This License gives no permission to license the work in any other way, but it does not invalidate such permission if you have separately received it.
 * d) If the work has interactive user interfaces, each must display Appropriate Legal Notices; however, if the Program has interactive interfaces that do not display Appropriate Legal Notices, your work need not make them do so.

A compilation of a covered work with other separate and independent works, which are not by their nature extensions of the covered work, and which are not combined with it such as to form a larger program, in or on a volume of a storage or distribution medium, is called an &#x201c;aggregate&#x201d; if the compilation and its resulting copyright are not used to limit the access or legal rights of the compilation's users beyond what the individual works permit. Inclusion of a covered work in an aggregate does not cause this License to apply to the other parts of the aggregate.

6. Conveying Non-Source Forms.

You may convey a covered work in object code form under the terms of sections 4 and 5, provided that you also convey t he machine-readable Corresponding Source under the terms of this License, in one of these ways:

 * a) Convey the object code in, or embodied in, a physical product (including a physical distribution medium), accompanied by the Corresponding Source fixed on a durable physical medium customarily used for software interchange.
 * b) Convey the object code in, or embodied in, a physical product (including a physical distribution medium), accompanied by a written offer, valid for at least three years and valid for as long as you offer spare parts or customer support for that product model, to give anyone who possesses the object code either (1) a copy of the Corresponding Source for all the software in the product that is covered by this License, on a durable physical medium customarily used for software interchange, for a price no more than your reasonable cost of physically performing this conveying of source, or (2) access to copy the Corresponding Source from a network server at no charge.
 * c) Convey individual copies of the object code with a copy of the written offer to provide the Corresponding Source. This alternative is allowed only occasionally and noncommercially, and only if you received the object code with such an offer, in accord with subsection 6b.
 * d) Convey the object code by offering access from a designated place (gratis or for a charge), and offer equivalent access to the Corresponding Source in the same way through the same place at no further charge. You need not require recipients to copy the Corresponding Source along with the object code. If the place to copy the object code is a network server, the Corresponding Source may be on a different server (operated by you or a third party) that supports equivalent copying facilities, provided you maintain clear directions next to the object code saying where to find the Corresponding Source. Regardless of what server hosts the Corresponding Source, you remain obligated to ensure that it is available for as long as needed to satisfy these requirements.

 * e) Convey the object code using peer-to-peer transmission, provided you inform other peers where the object code and Corresponding Source of the work are being offered to the general public at no charge under subsection 6d.

A separable portion of the object code, whose source code is excluded from the Corresponding Source as a System Library, need not be included in conveying the object code work.

A &#x201c;User Product&#x201d; is either (1) a &#x201c;consumer product&#x201d;, which means any tangible personal property which is normally used for personal, family, or household purposes, or (2) anything designed or sold for incorporation into a dwelling. In determining whether a product is a consumer product, doubtful cases shall be resolved in favor of coverage. For a particular product received by a particular user, &#x201c;normally used&#x201d; refers to a typical or common use of that class of product, regardless of the status of the particular user or of the way in which the particular user actually uses, or expects or is expected to use, the product. A product is a consumer product regardless of whether the product has substantial commercial, industrial or non-consumer uses, unless such uses represent the only significant mode of use of the product.

&#x201c;Installation Information&#x201d; for a User Product means any methods, procedures, authorization keys, or other information required to install and execute modified versions of a covered work in that User Product from a modified version of its Corresponding Source. The information must suffice to ensure that the continued functioning of the modified object code is in no case prevented or interfered with solely because modification has been made.

If you convey an object code work under this section in, or with, or specifically for use in, a User Product, and the conveying occurs as part of a transaction in which the right of possession and use of the User Product is transferred to the recipient in perpetuity or for a fixed term (regardless of how the transaction is characterized), the Corresponding Source conveyed under this section must be accompanied by the Installation Information. But this requirement does not apply if neither you nor any third party retains the ability to install modified object code on the User Product (for example, the work has been installed in ROM).

The requirement to provide Installation Information does not include a requirement to continue to provide support service, warranty, or updates for a work that has been modified or installed by the recipient, or for the User Product in which it has been modified or installed. Access to a network may be denied when the modification itself materially and adversely affects the operation of the network or violates the rules and protocols for communication across the network.

Corresponding Source conveyed, and Installation Information provided, in accord with this section must be in a format that is publicly documented (and with an implementation available to the public in source code form), and must require no special password or key for unpacking, reading or copying.

7. Additional Terms.

&#x201c;Additional permissions&#x201d; are terms that supplement the terms of this License by making exceptions from one or more of its conditions. Additional permissions that are applicable to the entire Program shall be treated as though they were included in this License, to the extent that they are valid under applicable law. If additional permissions apply only to part of the Program, that part may be used separately under those permissions, but the entire Program remains governed by this License without regard to the additional permissions.

When you convey a copy of a covered work, you may at your option remove any additional permissions from that copy, or from any part of it. (Additional permissions may be written to require their own removal in certain cases when you modify the work.) You may place additional permissions on material, added by you to a covered work, for which you have or can give appropriate copyright permission.

Notwithstanding any other provision of this License, for material you add to a covered work, you may (if authorized by the copyright holders of that material) supplement the terms of this License with terms:

 * a) Disclaiming warranty or limiting liability differently from the terms of sections 15 and 16 of this License; or
 * b) Requiring preservation of specified reasonable legal notices or author attributions in that material or in the Appropriate Legal Notices displayed by works containing it; or
 * c) Prohibiting misrepresentation of the origin of that material, or requiring that modified versions of such material be marked in reasonable ways as different from the original version; or
 * d) Limiting the use for publicity purposes of names of licensors or authors of the material; or
 * e) Declining to grant rights under trademark law for use of some trade names, trademarks, or service marks; or
 * f) Requiring indemnification of licensors and authors of that material by anyone who conveys the material (or modified versions of it) with contractual assumptions of liability to the recipient, for any liability that these contractual assumptions directly impose on those licensors and authors.

All other non-permissive additional terms are considered &#x201c;further restrictions&#x201d; within the meaning of section 10. If the Program as you received it, or any part of it, contains a notice stating that it is governed by this License along with a term that is a further restriction, you may remove that term. If a license document contains a further restriction but permits relicensing or conveying under this License, you may add to a covered work material governed by the terms of that license document, provided that the further restriction does not survive such relicensing or conveying.

If you add terms to a covered work in accord with this section, you must place, in the relevant source files, a statement of the additional terms that apply to those files, or a notice indicating where to find the applicable terms.

Additional terms, permissive or non-permissive, may be stated in the form of a separately written license, or stated as exceptions; the above requirements apply either way.

8. Termination.

You may not propagate or modify a covered work except as expressly provided under this License. Any attempt otherwise to propagate or modify it is void, and will automatically terminate your rights under this License (including any patent licenses granted under the third paragraph of section 11).

However, if you cease all violation of this License, then your license from a particular copyright holder is reinstated (a) provisionally, unless and until the copyright holder explicitly and finally terminates your license, and (b) permanently, if the copyright holder fails to notify you of the violation by some reasonable means prior to 60 days after the cessation.

Moreover, your license from a particular copyright holder is reinstated permanently if the copyright holder notifies you of the violation by some reasonable means, this is the first time you have received notice of violation of this License (for any work) from that copyright holder, and you cure the violation prior to 30 days after your receipt of the notice.

Termination of your rights under this section does not terminate the licenses of parties who have received copies or rights from you under this License. If your rights have been terminated and not permanently reinstated, you do not qualify to receive new licenses for the same material under section 10.

9. Acceptance Not Required for Having Copies.

You are not required to accept this License in order to receive or run a copy of the Program. Ancillary propagation of a covered work occurring solely as a consequence of using peer-to-peer transmission to receive a copy likewise does not require acceptance. However, nothing other than this License grants you permission to propagate or modify any covered work. These actions infringe copyright if you do not accept this License. Therefore, by modifying or propagating a covered work, you indicate your acceptance of this License to do so.

10. Automatic Licensing of Downstream Recipients.

Each time you convey a covered work, the recipient automatically receives a license from the original licensors, to run, modify and propagate that work, subject to this License. You are not responsible for enforcing compliance by third parties with this License.

An &#x201c;entity transaction&#x201d; is a transaction transferring control of an organization, or substantially all assets of one, or subdividing an organization, or merging organizations. If propagation of a covered work results from an entity transaction, each party to that transaction who receives a copy of the work also receives whatever licenses to the work the party's predecessor in interest had or could give under the previous paragraph, plus a right to possession of the Corresponding Source of the work from the predecessor in interest, if the predecessor has it or can get it with reasonable efforts.

You may not impose any further restrictions on the exercise of the rights granted or affirmed under this License. For example, you may not impose a license fee, royalty, or other charge for exercise of rights granted under this License, and you may not initiate litigation (including a cross-claim or counterclaim in a lawsuit) alleging that any patent claim is infringed by making, using, selling, offering for sale, or importing the Program or any portion of it.

11. Patents.

A &#x201c;contributor&#x201d; is a copyright holder who authorizes use under this License of the Program or a work on which the Program is based. The work thus licensed is called the contributor's &#x201c;contributor version&#x201d;.

A contributor's &#x201c;essential patent claims&#x201d; are all patent claims owned or controlled by the contributor, whether already acquired or hereafter acquired, that would be infringed by some manner, permitted by this License, of making, using, or selling its contributor version, but do not include claims that would be infringed only as a consequence of further modification of the contributor version. For purposes of this definition, &#x201c;control&#x201d; includes the right to grant patent sublicenses in a manner consistent with the requirements of this License.

Each contributor grants you a non-exclusive, worldwide, royalty-free patent license under the contributor's essential patent claims, to make, use, sell, offer for sale, import and otherwise run, modify and propagate the contents of its contributor version.

In the following three paragraphs, a &#x201c;patent license&#x201d; is any express agreement or commitment, however denominated, not to enforce a patent (such as an express permission to practice a patent or covenant not to sue for patent infringement). To &#x201c;grant&#x201d; such a patent license to a party means to make such an agreement or commitment not to enforce a patent against the party.

If you convey a covered work, knowingly relying on a patent license, and the Corresponding Source of the work is not available for anyone to copy, free of charge and under the terms of this License, through a publicly available network server or other readily accessible means, then you must either (1) cause the Corresponding Source to be so available, or (2) arrange to deprive yourself of the benefit of the patent license for this particular work, or (3) arrange, in a manner consistent with the requirements of this License, to extend the patent license to downstream recipients. &#x201c;Knowingly relying&#x201d; means you have actual knowledge that, but for the patent license, your conveying the covered work in a country, or your recipient's use of the covered work in a country, would infringe one or more identifiable patents in that country that you have reason to believe are valid.

If, pursuant to or in connection with a single transaction or arrangement, you convey, or propagate by procuring conveyance of, a covered work, and grant a patent license to some of the parties receiving the covered work authorizing them to use, propagate, modify or convey a specific copy of the covered work, then the patent license you grant is automatically extended to all recipients of the covered work and works based on it.

A patent license is &#x201c;discriminatory&#x201d; if it does not include within the scope of its coverage, prohibits the exercise of, or is conditioned on the non-exercise of one or more of the rights that are specifically granted under this License. You may not convey a covered work if you are a party to an arrangement with a third party that is in the business of distributing software, under which you make payment to the third party based on the extent of your activity of conveying the work, and under which the third party grants, to any of the parties who would receive the covered work from you, a discriminatory patent license (a) in connection with copies of the covered work conveyed by you (or copies made from those copies), or (b) primarily for and in connection with specific products or compilations that contain the covered work, unless you entered into that arrangement, or that patent license was granted, prior to 28 March 2007.

Nothing in this License shall be construed as excluding or limiting any implied license or other defenses to infringement that may otherwise be available to you under applicable patent law.

12. No Surrender of Others' Freedom.

If conditions are imposed on you (whether by court order, agreement or otherwise) that contradict the conditions of this License, they do not excuse you from the conditions of this License. If you cannot convey a covered work so as to satisfy simultaneously your obligations under this License and any other pertinent obligations, then as a consequence you may not convey it at all. For example, if you agree to terms that obligate you to collect a royalty for further conveying from those to whom you convey the Program, the only way you could satisfy both those terms and this License would be to refrain entirely from conveying the Program.

13. Use with the GNU Affero General Public License.

Notwithstanding any other provision of this License, you have permission to link or combine any covered work with a work licensed under version 3 of the GNU Affero General Public License into a single combined work, and to convey the resulting work. The terms of this License will continue to apply to the part which is the covered work, but the special requirements of the GNU Affero General Public License, section 13, concerning interaction through a network will apply to the combination as such.

14. Revised Versions of this License.

The Free Software Foundation may publish revised and/or new versions of the GNU General Public License from time to time. Such new versions will be similar in spirit to the present version, but may differ in detail to address new problems or concerns.

Each version is given a distinguishing version number. If the Program specifies that a certain numbered version of the GNU General Public License &#x201c;or any later version&#x201d; applies to it, you have the option of following the terms and conditions either of that numbered version or of any later version published by the Free Software Foundation. If the Program does not specify a version number of the GNU General Public License, you may choose any version ever published by the Free Software Foundation.

If the Program specifies that a proxy can decide which future versions of the GNU General Public License can be used, that proxy's public statement of acceptance of a version permanently authorizes you to choose that version for the Program.

Later license versions may give you additional or different permissions. However, no additional obligations are imposed on any author or copyright holder as a result of your choosing to follow a later version.

15. Disclaimer of Warranty.

THERE IS NO WARRANTY FOR THE PROGRAM, TO THE EXTENT PERMITTED BY APPLICABLE LAW. EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT HOLDERS AND/OR OTHER PARTIES PROVIDE THE PROGRAM &#x201c;AS IS&#x201d; WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION.

16. Limitation of Liability.

IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN WRITING WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MODIFIES AND/OR CONVEYS THE PROGRAM AS PERMITTED ABOVE, BE LIABLE TO YOU FOR DAMAGES, INCLUDING ANY GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OR INABILITY TO USE THE PROGRAM (INCLUDING BUT NOT LIMITED TO LOSS OF DATA OR DATA BEING RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD PARTIES OR A FAILURE OF THE PROGRAM TO OPERATE WITH ANY OTHER PROGRAMS), EVEN IF SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.

17. Interpretation of Sections 15 and 16.

If the disclaimer of warranty and limitation of liability provided above cannot be given local legal effect according to their terms, reviewing courts shall apply local law that most closely approximates an absolute waiver of all civil liability in connection with the Program, unless a warranty or assumption of liability accompanies a copy of the Program in return for a fee.

END OF TERMS AND CONDITIONS</pre>";
	}
    // Kernel Sub pointing to properly
	/** kernel()
		Redirects as the sub information is currently incorrect.
		This is because the class files go to post, but it only
		tries to kernel_post.  The sub is kernel_update though.
	*/
    public function kernel()
    {
        $this->kernel_update_post();
    }
	// Kernel Update
	/** kernel_update()
		Display's the published kernels for update.
		This information is obtained from the internet.
		Displays the default of Published kernels.
	*/
	public function kernel_update()
	{
		$this->kernelselForm('pk');
		print $this->FOGCore->FetchURL('http://freeghost.sourceforge.net/kernelupdates/index.php?version='.FOG_VERSION);
	}
	/** kernelselForm($type)
		Gives the user the option to select between:
		Published Kernels (from sourceforge)
		Unofficial Kernels (from mastacontrola.com)
	*/
	public function kernelselForm($type)
	{
		print "\n\t\t\t".'<div class="hostgroup">';
		print _("This section allows you to update the Linux kernel which is used to boot the client computers.  In FOG, this kernel holds all the drivers for the client computer, so if you are unable to boot a client you may wish to update to a newer kernel which may have more drivers built in.  This installation process may take a few minutes, as FOG will attempt to go out to the internet to get the requested Kernel, so if it seems like the process is hanging please be patient.");
		print "\n\t\t\t</div>";
		print "\n\t\t\t<div>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		print "\n\t\t\t".'<select name="kernelsel" onchange="this.form.submit()">';
		print "\n\t\t\t".'<option value="pk"'.($type == 'pk' ? ' selected="selected"' : '').'>'._('Published Kernels').'</option>';
		print "\n\t\t\t".'<option value="uk"'.($type == 'uk' ? ' selected="selected"' : '').'>'._('Unofficial Kernels').'</option>';
		print "\n\t\t\t</select>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t</div>";
	}
	// Kernel Update POST
	/** kernel_update_post()
		Displays the kernel based on the list selected.
		Defaults to published kernels.
	*/
	public function kernel_update_post()
	{
		if ($_REQUEST['sub'] == 'kernel-update')
		{
			switch ($_REQUEST['kernelsel'])
			{
				case 'pk':
					$this->kernelselForm('pk');
					print $this->FOGCore->FetchURL("http://freeghost.sourceforge.net/kernelupdates/index.php?version=" . FOG_VERSION);
					break;
				case 'uk':
					$this->kernelselForm('uk');
					print $this->FOGCore->FetchURL("http://mastacontrola.com/fogboot/kernel/index.php?version=" . FOG_VERSION);
					break;
				default:
					$this->kernelselForm('pk');
					print $this->FOGCore->FetchURL("http://freeghost.sourceforge.net/kernelupdates/index.php?version=" . FOG_VERSION);
					break;
			}
		}
		else if ( $_REQUEST["install"] == "1"  )
		{
			$_SESSION["allow_ajax_kdl"] = true;
			$_SESSION["dest-kernel-file"] = trim($_POST["dstName"]);
			$_SESSION["tmp-kernel-file"] = rtrim(sys_get_temp_dir(), '/') . '/' . basename( $_SESSION["dest-kernel-file"] );
			$_SESSION["dl-kernel-file"] = base64_decode($_REQUEST["file"]);
			if (file_exists($_SESSION["tmp-kernel-file"]))
				@unlink( $_SESSION["tmp-kernel-file"] );
			print "\n\t\t\t".'<div id="kdlRes">';
			print "\n\t\t\t".'<p id="currentdlstate">'._("Starting process...").'</p>';
			print "\n\t\t\t".'<img id="img" src="./images/loader.gif" />';
			print "\n\t\t\t</div>";
		}
		else
		{
			print "\n\t\t\t".'<form method="post" action="?node='.$_REQUEST['node'].'&sub=kernel&install=1&file='.$_REQUEST['file'].'">';
			print "\n\t\t\t<p>"._('New Kernel name:').'<input class="smaller" type="text" name="dstName" value="bzImage" /></p>';
			print "\n\t\t\t".'<p><input class="smaller" type="submit" value="Next" /></p>';
			print "\n\t\t\t</form>";
		}
	}
	// PXE Menu
	/** pxemenu()
		Displays the pxe/ipxe menu selections.
		Hidden menu requires user login from FOG GUI login.
		Also, hidden menu enforces a key press to access the menu.
		If none is selected, defaults to esc key.  Otherwise you 
		need to use the key combination chosen.
		Also used to setup the default timeout.  This time out is
		the timeout it uses to boot to the system.  If hidden menu
		is selected it sets both the hidden menu timeout and the menu,
		if none is selected, and the menu items.
	*/
	public function pxemenu()
	{
		// Set title
		$this->title = _('FOG PXE Boot Menu Configuration');
		// Headerdata
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
			array(),
		);
		// Templates
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			_('No Menu') => '<input type="checkbox" name="nomenu" ${noMenu} value="1" /><span class="icon icon-help hand" title="Option sets if there will even be the presence of a menu to the client systems.  If there is not a task set, it boots to the first device, if there is a task, it performs that task."></span>',
			_('Hide Menu') => '<input type="checkbox" name="hidemenu" ${checked} value="1" /><span class="icon icon-help hand" title="Option below sets the key sequence.  If none is specified, ESC is defaulted. Login with the FOG credentials and you will see the menu.  Otherwise it will just boot like normal."></span>',
			_('Boot Key Sequence') => '${boot_keys}',
			_('Menu Timeout (in seconds)').':*' => '<input type="text" name="timeout" value="${timeout}" id="timeout" />',
			_('Exit to Hard Drive Type') => '<select name="bootTypeExit"><option value="sanboot" '.($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'sanboot' ? 'selected="selected"' : '').'>Sanboot style</option><option value="exit" '.($this->FOGCore->getSetting('FOG_BOOT_EXIT_TYPE') == 'exit' ? 'selected="selected"' : '').'>Exit style</option></select>',
			'<a href="#" onload="$(\'#advancedTextArea\').hide();" onclick="$(\'#advancedTextArea\').toggle();" id="pxeAdvancedLink">Advanced Configuration Options</a>' => '<div id="advancedTextArea" class="hidden"><div class="lighterText tabbed">Add any custom text you would like included added as part of your <i>default</i> file.</div><textarea rows="5" cols="40" name="adv">${adv}</textarea></div>',
			'&nbsp;' => '<input type="submit" value="'._('Save PXE MENU').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
				'checked' => ($this->FOGCore->getSetting('FOG_PXE_MENU_HIDDEN') ? 'checked="checked"' : ''),
				'boot_keys' => $this->FOGCore->getClass('KeySequenceManager')->buildSelectBox($this->FOGCore->getSetting('FOG_KEY_SEQUENCE')),
				'timeout' => $this->FOGCore->getSetting('FOG_PXE_MENU_TIMEOUT'),
				'adv' => $this->FOGCore->getSetting('FOG_PXE_ADVANCED'),
				'noMenu' => ($this->FOGCore->getSetting('FOG_NO_MENU') ? 'checked="checked"' : ''),
			);
		}
		// Hook
		$this->HookManager->processEvent('PXE_BOOT_MENU', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	// PXE Menu: POST
	/** pxemenu_post()
		Performs the updates for the form sent from pxemenu().
	*/
	public function pxemenu_post()
	{
		try
		{
			$timeout = trim($_POST['timeout']);
			$timeout = (!empty($timeout) && is_numeric($timeout) && $timeout >= 0 ? true : false);
			if (!$timeout)
				throw new Exception(_("Invalid Timeout Value."));
			else
				$timeout = trim($_POST['timeout']);
			if ($this->FOGCore->setSetting('FOG_PXE_MENU_HIDDEN',$_REQUEST['hidemenu']) && $this->FOGCore->setSetting('FOG_PXE_MENU_TIMEOUT',$timeout) && $this->FOGCore->setSetting('FOG_PXE_ADVANCED',$_REQUEST['adv']) && $this->FOGCore->setSetting('FOG_KEY_SEQUENCE',$_REQUEST['keysequence']) && $this->FOGCore->setSetting('FOG_NO_MENU',$_REQUEST['nomenu']) && $this->FOGCore->setSetting('FOG_BOOT_EXIT_TYPE',$_REQUEST['bootTypeExit']))
				throw new Exception("PXE Menu has been updated!");
			else
				throw new Exception("PXE Menu update failed!");
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
	// Client Updater
	/** client_updater()
		You update the client files through here.
		This is used for the Host systems with FOG Service installed.
		Here is where you can update the files an push these files to
		the client.
	*/
	public function client_updater()
	{
		// Set title
		$this->title = _("FOG Client Service Updater");
		$this->headerData = array(
			_('Module Name'),
			_('Module MD5'),
			_('Module Type'),
			_('Delete'),
		);
		$this->templates = array(
			'<form method="post" action="${action}"><input type="hidden" name="name" value="FOG_SERVICE_CLIENTUPDATER_ENABLED" />${name}',
			'${module}',
			'${type}',
			'<input type="checkbox" onclick="this.form.submit()" name="delcu" class="delid" id="delcuid${client_id}" value="${client_id}" /><label for="delcuid${client_id}">Delete</label></form>',
		);
		$this->attributes = array(
			array(),
			array(),
			array(),
			array(),
		);
		print "\n\t\t\t".'<div class="hostgroup">';
		print _("This section allows you to update the modules and config files that run on the client computers.  The clients will checkin with the server from time to time to see if a new module is published.  If a new module is published the client will download the module and use it on the next time the service is started.");
		print "\n\t\t\t</div>";
		$ClientUpdates = $this->FOGCore->getClass('ClientUpdaterManager')->find('','name');
		foreach ((array)$ClientUpdates AS $ClientUpdate)
		{
			$this->data[] = array(
				'action' => $this->formAction.'&tab=clientupdater',
				'name' => $ClientUpdate->get('name'),
				'module' => $ClientUpdate->get('md5'),
				'type' => $ClientUpdate->get('type'),
				'client_id' => $ClientUpdate->get('id'),
			);
		}
		// Hook
		$this->HookManager->processEvent('CLIENT_UPDATE', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		// reset for next element
		unset($this->headerData,$this->attributes,$this->templates,$this->data);
		$this->headerData = array(
			_('Upload a new client module/configuration file'),
			''
		);
		$this->attributes = array(
			array(),
			array(),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$fields = array(
			'<input type="file" name="module[]" value="" multiple/> <span class="lightColor">'._('Max Size:').ini_get('post_max_size').'</span>' => '<input type="submit" value="'._('Upload File').'" />',
		);
		foreach ((array)$fields AS $field => $input)
		{
			$this->data[] = array(
				'field' => $field,
				'input' => $input,
			);
		}
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'&tab=clientupdater" enctype="multipart/form-data">';
		print "\n\t\t\t\t".'<input type="hidden" name="name" value="FOG_SERVICE_CLIENTUPDATER_ENABLED" />';
		// Hook
		$this->HookManager->processEvent('CLIENT_UPDATE', array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
		// Output
		$this->render();
		print '</form>';
	}
	// Client Updater: POST
	/** client_updater_post()
		Just updates the values set in client_updater().
	*/
	public function client_updater_post()
	{
		$Service = current($this->FOGCore->getClass('ServiceManager')->find(array('name' => $_REQUEST['name'])));
		$Service && $Service->isValid() ? $Service->set('value', $_REQUEST['en'] ? 1 : 0)->save() : null;
		if ($_REQUEST['delcu'])
		{
			$ClientUpdater = new ClientUpdater($_REQUEST['delcu']);
			$ClientUpdater->destroy();
			$this->FOGCore->setMessage(_('Client module update deleted!'));
		}
		if ($_FILES['module'])
		{
			foreach((array)$_FILES['module']['tmp_name'] AS $index => $tmp_name)
			{
				if (file_exists($_FILES['module']['tmp_name'][$index]))
				{
					$ClientUpdater = current($this->FOGCore->getClass('ClientUpdaterManager')->find(array('name' => $_FILES['module']['name'][$index])));
					if(file_get_contents($_FILES['module']['tmp_name'][$index]))
					{
						if ($ClientUpdater)
						{
							$ClientUpdater->set('name',basename($_FILES['module']['name'][$index]))
								->set('md5',md5(file_get_contents($_FILES['module']['tmp_name'][$index])))
								->set('type',($this->FOGCore->endsWith($_FILES['module']['name'][$index],'.ini') ? 'txt' : 'bin'))
								->set('file',file_get_contents($_FILES['module']['tmp_name'][$index]));
						}
						else
						{
							$ClientUpdater = new ClientUpdater(array(
								'name' => basename($_FILES['module']['name'][$index]),
								'md5' => md5(file_get_contents($_FILES['module']['tmp_name'][$index])),
								'type'=> ($this->FOGCore->endsWith($_FILES['module']['name'][$index],'.ini') ? 'txt' : 'bin'),
								'file' => file_get_contents($_FILES['module']['tmp_name'][$index]),
							));
						}
						if ($ClientUpdater->save())
							$this->FOGCore->setMessage('Modules Added/Updated!');
					}
				}
			}
		}
		$this->FOGCore->redirect(sprintf('?node=%s&sub=%s#%s', $_REQUEST['node'], $_REQUEST['sub'], $_REQUEST['tab']));
	}
	// MAC Address List
	/** mac_list()
		This is where you update the mac address listing.
		If you choose to update, it downloads the latest oui.txt file
		from http://standards.ieee.org/regauth/oui/oui.txt.
		
		Then it updates the database with these values.
	*/
	public function mac_list()
	{
		// Set title
		$this->title = _("MAC Address Manufacturer Listing");
        // Allow the updating and deleting of the mac-lists.
        $this->mac_list_post();
		print "\n\t\t\t".'<div class="hostgroup">';
		print "\n\t\t\t\t"._('This section allows you to import known mac address makers into the FOG database for easier identification.');
		print "\n\t\t\t</div>";
		print "\n\t\t\t<div>";
		print "\n\t\t\t\t<p>"._('Current Records: ').$this->FOGCore->getMACLookupCount().'</p>';
		print "\n\t\t\t\t<p>".'<input type="button" id="delete" value="'._('Delete Current Records').'" onclick="clearMacs()" /><input style="margin-left: 20px" type="button" id="update" value="'._('Update Current Listing').'" onclick="updateMacs()" /></p>';
		print "\n\t\t\t\t<p>"._('MAC address listing source: ').'<a href="http://standards.ieee.org/regauth/oui/oui.txt">http://standards.ieee.org/regauth/oui/oui.txt</a></p>';
		print "\n\t\t\t</div>";
	}
	// MAC Address List: POST
	/** mac_list_post()
		This just performs the actions when mac_list() is updated.
	*/
	public function mac_list_post()
	{
		if ( $_GET["update"] == "1" )
		{
			$f = "./other/oui.txt";
			exec('rm -rf '.BASEPATH.'/management/other/oui.txt');
			exec('wget -P '.BASEPATH.'/management/other/ http://standards.ieee.org/develop/regauth/oui/oui.txt');
			if ( file_exists($f) )
			{
				$handle = fopen($f, "r");
				$start = 18;
				$imported = 0;
				while (!feof($handle)) 
				{
					$line = trim(fgets($handle));
					if ( preg_match( "#^([0-9a-fA-F][0-9a-fA-F][:-]){2}([0-9a-fA-F][0-9a-fA-F]).*$#", $line ) )
					{
						$macprefix = substr( $line, 0, 8 );					
						$maker = substr( $line, $start, strlen( $line ) - $start );
						try
						{
							if ( strlen(trim( $macprefix ) ) == 8 && strlen($maker) > 0 )
							{
								if ( $this->FOGCore->addUpdateMACLookupTable( $macprefix, $maker ) )
									$imported++;
							}
						}
						catch ( Exception $e )
						{
							print ($e->getMessage()."<br />");
						}
						
					}
				}
				fclose($handle);
				$this->FOGCore->setMessage($imported._(' mac addresses updated!'));
			}
			else
				print (_("Unable to locate file: $f"));
		}
		else if ($_GET["clear"] == "1")
			$this->FOGCore->clearMACLookupTable();
	}
	// FOG System Settings
	/** settings()
		This is where you set the values for FOG itself.  You can update
		both the default service information and global information beyond
		services.  The default kernel, the fog user information, etc...
		Major things of note is that the system is now more user friendly.
		Meaning, off/on values are checkboxes, items that are more specific
		(e.g. image setting, default view,) are now select boxes.  This should
		help limit typos in the old text based system.
		Passwords are blocked with the password form field.
	*/
	public function settings()
	{
		$ServiceNames = array(
			'FOG_PXE_MENU_HIDDEN',
			'FOG_QUICKREG_AUTOPOP',
			'FOG_SERVICE_AUTOLOGOFF_ENABLED',
			'FOG_SERVICE_CLIENTUPDATER_ENABLED',
			'FOG_SERVICE_DIRECTORYCLEANER_ENABLED',
			'FOG_SERVICE_DISPLAYMANAGER_ENABLED',
			'FOG_SERVICE_GREENFOG_ENABLED',
			'FOG_SERVICE_HOSTREGISTER_ENABLED',
			'FOG_SERVICE_HOSTNAMECHANGER_ENABLED',
			'FOG_SERVICE_PRINTERMANAGER_ENABLED',
			'FOG_SERVICE_SNAPIN_ENABLED',
			'FOG_SERVICE_TASKREBOOT_ENABLED',
			'FOG_SERVICE_USERCLEANUP_ENABLED',
			'FOG_SERVICE_USERTRACKER_ENABLED',
			'FOG_ADVANCED_STATISTICS',
			'FOG_CHANGE_HOSTNAME_EARLY',
			'FOG_DISABLE_CHKDSK',
			'FOG_HOST_LOOKUP',
			'FOG_UPLOADIGNOREPAGEHIBER',
			'FOG_USE_ANIMATION_EFFECTS',
			'FOG_USE_LEGACY_TASKLIST',
			'FOG_USE_SLOPPY_NAME_LOOKUPS',
			'FOG_PLUGINSYS_ENABLED',
			'FOG_LEGACY_FLAG_IN_GUI',
			'FOG_NO_MENU',
			'FOG_MINING_ENABLE',
			'FOG_MINING_FULL_RUN_ON_WEEKEND',
		);
		// Set title
		$this->title = _("FOG System Settings");
		print "\n\t\t\t".'<p class="hostgroup">'._("This section allows you to customize or alter the way in which FOG operates.  Please be very careful changing any of the following settings, as they can cause issues that are difficult to troubleshoot.").'</p>';
		print "\n\t\t\t\t".'<form method="post" action="'.$this->formAction.'">';
		print "\n\t\t\t\t".'<div id="tab-container-1">';
		// Header Data
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array('width' => 270,'height' => 35),
			array(),
			array('class' => 'r'),
		);
		// Templates
		$this->templates = array(
			'${service_name}',
			'${input_type}',
			'${span}',
		);
		$ServiceCats = $this->FOGCore->getClass('ServiceManager')->getSettingCats();
		foreach ((array)$ServiceCats AS $ServiceCAT)
		{
			
			$divTab = preg_replace('/[[:space:]]/','_',preg_replace('/:/','_',$ServiceCAT));
			print "\n\t\t\t\t\t\t".'<a id="'.$divTab.'" style="text-decoration:none;" href="#'.$divTab.'"><h3>'.$ServiceCAT.'</h3></a>';
			print "\n\t\t\t".'<div id="'.$divTab.'">';
			$ServMan = $this->FOGCore->getClass('ServiceManager')->find(array('category' => $ServiceCAT),'AND','id');
			foreach ((array)$ServMan AS $Service)
			{
				if ($Service->get('name') == 'FOG_PIGZ_COMP')
					$type = '<div id="pigz" style="width: 200px; top: 15px;"></div><input type="text" readonly="true" name="${service_id}" id="showVal" maxsize="1" style="width: 10px; top: -5px; left:225px; position: relative;" value="${service_value}" />';
					//$type = '<input type="range" name="${service_id}" id="pigz" min="0" max="9" value="${service_value}" autocomplete="off" style="width: 200px;" /><input id="showVal" type="text" maxsize="1" value="${service_value}" disabled style="width: 10px" />';
				else if (preg_match('#(pass|PASS)#i',$Service->get('name')) && !preg_match('#(VALID|MIN)#i',$Service->get('name')))
					$type = '<input type="password" name="${service_id}" value="${service_value}" />';
				else if ($Service->get('name') == 'FOG_VIEW_DEFAULT_SCREEN')
				{
					foreach(array('SEARCH','LIST') AS $viewop)
						$options[] = '<option value="'.strtolower($viewop).'" '.($Service->get('value') == strtolower($viewop) ? 'selected="selected"' : '').'>'.$viewop.'</option>';
					$type = "\n\t\t\t".'<select name="${service_id}" style="width: 220px" autocomplete="off">'."\n\t\t\t\t".implode("\n",$options)."\n\t\t\t".'</select>';
					unset($options);
				}
				else if ($Service->get('name') == 'FOG_BOOT_EXIT_TYPE')
				{
					foreach(array('sanboot','exit') AS $viewop)
						$options[] = '<option value=".'.$viewop.'" '.($Service->get('value') == $viewop ? 'selected="selected"' : '').'>'.strtoupper($viewop).'</option>';
					$type = "\n\t\t\t".'<select name="${service_id}" style="width: 220px" autocomplete="off">'."\n\t\t\t\t".implode("\n",$options)."\n\t\t\t".'</select>';
					unset($options);
				}
				else if (in_array($Service->get('name'),$ServiceNames))
					$type = '<input type="checkbox" name="${service_id}" value="1" '.($Service->get('value') ? 'checked="checked"' : '').' />';
				else if ($Service->get('name') == 'FOG_DEFAULT_LOCALE')
				{
					foreach((array)$GLOBALS['foglang']['Language'] AS $lang => $humanreadable)
					{
						if ($lang == 'en')
							$lang = 'en_US.UTF-8';
						else if ($lang == 'zh')
							$lang = 'zh_CN.UTF-8';
						else if ($lang == 'it')
							$lang = 'it_IT.UTF-8';
						else if ($lang == 'fr')
							$lang = 'fr_FR.UTF-8';
						else if ($lang == 'es')
							$lang = 'es_ES.UTF-8';
						$options2[] = '<option value="'.$lang.'" '.($this->FOGCore->getSetting('FOG_DEFAULT_LOCALE') == $lang ? 'selected="selected"' : '').'>'.$humanreadable.'</option>';
					}
					$type = "\n\t\t\t".'<select name="${service_id}" autocomplete="off" style="width: 220px">'."\n\t\t\t\t".implode("\n",$options2)."\n\t\t\t".'</select>';
				}
				else if ($Service->get('name') == 'FOG_QUICKREG_IMG_ID')
					$type = $this->FOGCore->getClass('ImageManager')->buildSelectBox($this->FOGCore->getSetting('FOG_QUICKREG_IMG_ID'),$Service->get('id'));
				else if ($Service->get('name') == 'FOG_KEY_SEQUENCE')
					$type = $this->FOGCore->getClass('KeySequenceManager')->buildSelectBox($this->FOGCore->getSetting('FOG_KEY_SEQUENCE'),$Service->get('id'));
				else if ($Service->get('name') == 'FOG_QUICKREG_OS_ID')
				{
					if ($this->FOGCore->getSetting('FOG_QUICKREG_IMG_ID') > 0)
						$Image = new Image($this->FOGCore->getSetting('FOG_QUICKREG_IMG_ID'));
					$type = '<p>'.($Image && $Image->isValid() ? $Image->getOS()->get('name') : _('No image specified')).'</p>';
				}
				else
					$type = '<input type="text" name="${service_id}" value="${service_value}" />';
				$this->data[] = array(
					'service_name' => $Service->get('name'),
					'input_type' => (count(explode(chr(10),$Service->get('value'))) <= 1 ? $type : '<textarea name="${service_id}">${service_value}</textarea>'),
					'span' => '<span class="icon icon-help hand" title="${service_desc}"></span>',
					'service_id' => $Service->get('id'),
					'service_value' => $Service->get('value'),
					'service_desc' => $Service->get('description'),
				);
			}
			$this->data[] = array(
				'span' => '&nbsp;',
				'service_name' => '<input type="hidden" value="1" name="update" />',
				'input_type' => '<input type="submit" value="'._('Save Changes').'" />',
			);
			// Hook
			$this->HookManager->processEvent('CLIENT_UPDATE_'.$ServiceCAT, array('data' => &$this->data, 'templates' => &$this->templates, 'attributes' => &$this->attributes));
			// Output
			$this->render();
			print "\n\t\t\t\t\t</div>";
			unset($this->data);
		}
		print "\n\t\t\t\t\t</div>";
		print "\n\t\t\t\t</form>";
	}
	// FOG System Settings: POST
	/** settings_post()
		Updates the settings set from the fields.
	*/
	public function settings_post()
	{
		$ServiceMan = $this->FOGCore->getClass('ServiceManager')->find();
		foreach ((array)$ServiceMan AS $Service)
			$key[] = $Service->get('id');
		foreach ((array)$key AS $key)
		{
			$Service = new Service($key);
			if ($Service->get('name') == 'FOG_QUICKREG_IMG_ID' && empty($_REQUEST[$key]))
				$Service->set('value',-1)->save();
			else if ($Service->get('name') == 'FOG_USER_VALIDPASSCHARS')
				$Service->set('value',addslashes($_REQUEST[$key]))->save();
			else
				$Service->set('value',$_REQUEST[$key])->save();
		}
		$this->FOGCore->setMessage('Settings Successfully stored!');
		$this->FOGCore->redirect(sprintf('?node=%s&sub=%s',$_REQUEST['node'],$_REQUEST['sub']));
	}
	// Log Viewer
	/** log()
		Views the log files for the FOG Services on the server (FOGImageReplicator, FOGTaskScheduler, FOGMulticastManager).
		Just used to view these logs.  Can be used for more than this as well with some tweeking.
	*/
	public function log()
	{
		// Set title
		$this->title = "FOG Log Viewer";
		print "\n\t\t\t<p>";
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'">';

		print "\n\t\t\t<p>"._('File:');
		foreach (array('Multicast','Scheduler','Replicator') AS $value)
			$options3[] = "\n\t\t\t\t".'<option '.($value == $_POST['logtype'] ? 'selected="selected"' : '').' value="'.$value.'">'.$value.'</option>';
		print "\n\t\t\t".'<select name="logtype">'.implode("\n\t\t\t\t",$options3)."\n\t\t\t".'</select>';
		print "\n\t\t\t"._('Number of lines:');
		foreach (array(20, 50, 100, 200, 400, 500, 1000) AS $value)
			$options4[] = '<option '.($value == $_POST['n'] ? 'selected="selected"' : '').' value="'.$value.'">'.$value.'</option>';
		print "\n\t\t\t".'<select name="n">'.implode("\n\t\t\t\t",$options4)."\n\t\t\t".'</select>';
		print "\n\t\t\t".'<input type="submit" value="'._('Refresh').'" />';
		print "\n\t\t\t</p>";
		print "\n\t\t\t</form>";
		print "\n\t\t\t".'<div class="sub l">';
		print "\n\t\t\t\t<pre>";
		$n = 20;
		if ( $_POST["n"] != null && is_numeric($_POST["n"]) )
			$n = $_POST["n"];
		$t = trim($_POST["logtype"]);
		$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/multicast.log";
		if ( $t == "Multicast" )
			$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/multicast.log";
		else if ( $t == "Scheduler" )
			$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/fogscheduler.log";
		else if ( $t == "Replicator" )
			$logfile = $GLOBALS['FOGCore']->getSetting( "FOG_UTIL_BASE" ) . "/log/fogreplicator.log";				
		system("tail -n $n \"$logfile\"");
		print "\n\t\t\t\t</pre>";
		print "\n\t\t\t</div>";
		print "\n\t\t\t</p>";
	}
	/** config()
		This feature is relatively new.  It's a means for the user to save the fog database
		and/or replace the current one with your own, say if it's a fresh install, but you want
		the old information restored.
	*/
	public function config()
	{
		$this->HookManager->processEvent('IMPORT');
		$this->title='Configuration Import/Export';
		$report = new ReportMaker();
		$_SESSION['foglastreport']=serialize($report);
		unset($this->data,$this->headerData);
		$this->attributes = array(
			array(),
			array('class' => 'r'),
		);
		$this->templates = array(
			'${field}',
			'${input}',
		);
		$this->data[0] = array(
			'field' => _('Click the button to export the database.'),
			'input' => '<input type="hidden" name="backup" value="1" /><input type="submit" value="'._('Export').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="export.php?type=sql">';
		$this->render();
		unset($this->data);
		print '</form>';
		$this->data[0] = array(
			'field' => _('Import a previous backup file.'),
			'input' => '<span class="lightColor">Max Size: ${size}</span><input type="file" name="dbFile" />',
			'size' => ini_get('post_max_size'),
		);
		$this->data[1] = array(
			'field' => null,
			'input' => '<input type="submit" value="'._('Import').'" />',
		);
		print "\n\t\t\t".'<form method="post" action="'.$this->formAction.'" enctype="multipart/form-data">';
		$this->render();
		unset($this->data);
		print "</form>";
	}
	/** config_post()
		Imports the file and installs the file as needed.
	*/
	public function config_post()
	{
		$this->HookManager->processEvent('IMPORT_POST');
		//POST
		try
		{
			if($_FILES['dbFile'] != null)
			{
				$dbFileName = BASEPATH.'/management/other/'.basename($_FILES['dbFile']['name']);
				if(move_uploaded_file($_FILES['dbFile']['tmp_name'], $dbFileName))
					print "\n\t\t\t<h2>"._('File Import successful!').'</h2>';
				else
					throw new Exception('Could not upload file!');
				exec('mysql -u' . DATABASE_USERNAME . ' -p' . DATABASE_PASSWORD . ' -h'.DATABASE_HOST.' '.DATABASE_NAME.' < '.$dbFileName);
				print "\n\t\t\t<h2>"._('Database Added!').'</h2>';
				exec('rm -rf '.$dbFileName);
			}
		}
		catch (Exception $e)
		{
			$this->FOGCore->setMessage($e->getMessage());
			$this->FOGCore->redirect($this->formAction);
		}
	}
}
// Register page with FOGPageManager
$FOGPageManager->register(new FOGConfigurationPage());
