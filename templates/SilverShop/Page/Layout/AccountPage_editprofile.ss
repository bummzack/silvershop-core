<% require css("silvershop/core: css/account.css") %>

<% include SilverShop\Includes\AccountNavigation %>
<div id="Account" class="typography">

    <h2 class="pagetitle">
        <%t AccountPage_EditProfile.Title 'Edit Profile' %>
    </h2>

    $EditAccountForm
    $ChangePasswordForm

</div>
