<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="https://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml"
    xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <!--[if !mso]><!-- -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!--<![endif]-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="format-detection" content="telephone=no" />
    <meta name="format-detection" content="date=no" />
    <meta name="format-detection" content="address=no" />
    <meta name="format-detection" content="email=no" />
    <meta name="x-apple-disable-message-reformatting" />
    <link href="https://fonts.googleapis.com/css?family=Montserrat:ital,wght@0,600;0,700" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Poppins:ital,wght@0,400;0,400;0,600" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css?family=Fira+Sans:ital,wght@0,400;0,500;0,700" rel="stylesheet" />
    <title>{{ $subject }}</title>

    <style>
        @media all {
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 400;
                src: local('Fira Sans Regular'), local('FiraSans-Regular'), url(https://fonts.gstatic.com/s/firasans/v10/va9E4kDNxMZdWfMOD5VvmojLazX3dGTP.woff2) format('woff2');
                unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
            }

            /* cyrillic */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 400;
                src: local('Fira Sans Regular'), local('FiraSans-Regular'), url(https://fonts.gstatic.com/s/firasans/v10/va9E4kDNxMZdWfMOD5Vvk4jLazX3dGTP.woff2) format('woff2');
                unicode-range: U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
            }

            /* latin-ext */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 400;
                src: local('Fira Sans Regular'), local('FiraSans-Regular'), url(https://fonts.gstatic.com/s/firasans/v10/va9E4kDNxMZdWfMOD5VvmYjLazX3dGTP.woff2) format('woff2');
                unicode-range: U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
            }

            /* latin */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 400;
                src: local('Fira Sans Regular'), local('FiraSans-Regular'), url(https://fonts.gstatic.com/s/firasans/v10/va9E4kDNxMZdWfMOD5Vvl4jLazX3dA.woff2) format('woff2');
                unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }

            /* cyrillic-ext */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 500;
                src: local('Fira Sans Medium'), local('FiraSans-Medium'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnZKveSxf6Xl7Gl3LX.woff2) format('woff2');
                unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
            }

            /* cyrillic */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 500;
                src: local('Fira Sans Medium'), local('FiraSans-Medium'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnZKveQhf6Xl7Gl3LX.woff2) format('woff2');
                unicode-range: U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
            }

            /* latin-ext */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 500;
                src: local('Fira Sans Medium'), local('FiraSans-Medium'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnZKveSBf6Xl7Gl3LX.woff2) format('woff2');
                unicode-range: U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
            }

            /* latin */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 500;
                src: local('Fira Sans Medium'), local('FiraSans-Medium'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnZKveRhf6Xl7Glw.woff2) format('woff2');
                unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }

            /* cyrillic-ext */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 700;
                src: local('Fira Sans Bold'), local('FiraSans-Bold'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnLK3eSxf6Xl7Gl3LX.woff2) format('woff2');
                unicode-range: U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F;
            }

            /* cyrillic */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 700;
                src: local('Fira Sans Bold'), local('FiraSans-Bold'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnLK3eQhf6Xl7Gl3LX.woff2) format('woff2');
                unicode-range: U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
            }

            /* latin-ext */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 700;
                src: local('Fira Sans Bold'), local('FiraSans-Bold'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnLK3eSBf6Xl7Gl3LX.woff2) format('woff2');
                unicode-range: U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
            }

            /* latin */
            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 700;
                src: local('Fira Sans Bold'), local('FiraSans-Bold'), url(https://fonts.gstatic.com/s/firasans/v10/va9B4kDNxMZdWfMOD5VnLK3eRhf6Xl7Glw.woff2) format('woff2');
                unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
            }
        }
    </style>
    <!--<![endif]-->
    <style>
        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            min-height: 100% !important;
            width: 100% !important;
            -webkit-font-smoothing: antialiased;
        }

        * {
            -ms-text-size-adjust: 100%;
        }

        #outlook a {
            padding: 0;
        }

        .ReadMsgBody,
        .ExternalClass {
            width: 100%;
        }

        .ExternalClass,
        .ExternalClass p,
        .ExternalClass td,
        .ExternalClass div,
        .ExternalClass span,
        .ExternalClass font {
            line-height: 100%;
        }

        table,
        td,
        th {
            mso-table-lspace: 0 !important;
            mso-table-rspace: 0 !important;
            border-collapse: collapse;
        }

        u+.body table,
        u+.body td,
        u+.body th {
            will-change: transform;
        }

        body,
        td,
        th,
        p,
        div,
        li,
        a,
        span {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            mso-line-height-rule: exactly;
        }

        img {
            border: 0;
            outline: 0;
            line-height: 100%;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        a[x-apple-data-detectors] {
            color: inherit !important;
            text-decoration: none !important;
        }

        .pc-gmail-fix {
            display: none;
            display: none !important;
        }

        .body .pc-project-body {
            background-color: transparent !important;
        }

        @media (min-width: 621px) {
            .pc-lg-hide {
                display: none;
            }

            .pc-lg-bg-img-hide {
                background-image: none !important;
            }
        }
    </style>
    <style>
        @media (max-width: 620px) {
            .pc-project-body {
                min-width: 0px !important;
            }

            .pc-project-container {
                width: 100% !important;
            }

            .pc-sm-hide {
                display: none !important;
            }

            .pc-sm-bg-img-hide {
                background-image: none !important;
            }

            .pc-w620-width-120 {
                width: 120px !important;
            }

            .pc-w620-height-auto {
                height: auto !important;
            }

            .pc-w620-padding-30-30-30-30 {
                padding: 30px 30px 30px 30px !important;
            }

            .pc-w620-padding-32-24-32-24 {
                padding: 32px 24px 32px 24px !important;
            }

            table.pc-w620-spacing-0-0-0-0 {
                margin: 0px 0px 0px 0px !important;
            }

            td.pc-w620-spacing-0-0-0-0,
            th.pc-w620-spacing-0-0-0-0 {
                margin: 0 !important;
                padding: 0px 0px 0px 0px !important;
            }

            .pc-w620-width-80 {
                width: 80px !important;
            }

            .pc-w620-fontSize-32px {
                font-size: 32px !important;
            }

            .pc-w620-lineHeight-120pc {
                line-height: 120% !important;
            }

            table.pc-w620-spacing-0-0-16-0 {
                margin: 0px 0px 16px 0px !important;
            }

            td.pc-w620-spacing-0-0-16-0,
            th.pc-w620-spacing-0-0-16-0 {
                margin: 0 !important;
                padding: 0px 0px 16px 0px !important;
            }

            .pc-w620-padding-0-0-0-0 {
                padding: 0px 0px 0px 0px !important;
            }

            .pc-w620-fontSize-14px {
                font-size: 14px !important;
            }

            .pc-w620-lineHeight-140pc {
                line-height: 140% !important;
            }

            .pc-w620-itemsSpacings-0-30 {
                padding-left: 0px !important;
                padding-right: 0px !important;
                padding-top: 15px !important;
                padding-bottom: 15px !important;
            }

            table.pc-w620-spacing-0-0-32-0 {
                margin: 0px 0px 32px 0px !important;
            }

            td.pc-w620-spacing-0-0-32-0,
            th.pc-w620-spacing-0-0-32-0 {
                margin: 0 !important;
                padding: 0px 0px 32px 0px !important;
            }

            .pc-w620-fontSize-16 {
                font-size: 16px !important;
            }

            .pc-w620-lineHeight-163pc {
                line-height: 163% !important;
            }

            .pc-w620-itemsSpacings-0-10 {
                padding-left: 0px !important;
                padding-right: 0px !important;
                padding-top: 5px !important;
                padding-bottom: 5px !important;
            }

            .pc-w620-lineHeight-125pc {
                line-height: 125% !important;
            }

            .pc-w620-padding-10-35-10-35 {
                padding: 10px 35px 10px 35px !important;
            }

            .pc-w620-width-100pc {
                width: 100% !important;
            }

            .pc-w620-height-1 {
                height: 1px !important;
            }

            .pc-w620-itemsSpacings-0-24 {
                padding-left: 0px !important;
                padding-right: 0px !important;
                padding-top: 12px !important;
                padding-bottom: 12px !important;
            }

            .pc-w620-valign-middle {
                vertical-align: middle !important;
            }

            td.pc-w620-halign-center,
            th.pc-w620-halign-center {
                text-align: center !important;
            }

            table.pc-w620-halign-center {
                float: none !important;
                margin-right: auto !important;
                margin-left: auto !important;
            }

            img.pc-w620-halign-center {
                margin-right: auto !important;
                margin-left: auto !important;
            }

            .pc-w620-width-fill {
                width: 100% !important;
            }

            div.pc-w620-align-center,
            th.pc-w620-align-center,
            a.pc-w620-align-center,
            td.pc-w620-align-center {
                text-align: center !important;
                text-align-last: center !important;
            }

            table.pc-w620-align-center {
                float: none !important;
                margin-right: auto !important;
                margin-left: auto !important;
            }

            img.pc-w620-align-center {
                margin-right: auto !important;
                margin-left: auto !important;
            }

            .pc-w620-width-hug {
                width: auto !important;
            }

            .pc-w620-width-140 {
                width: 140px !important;
            }

            .pc-w620-itemsSpacings-40-0 {
                padding-left: 20px !important;
                padding-right: 20px !important;
                padding-top: 0px !important;
                padding-bottom: 0px !important;
            }

            .pc-w620-itemsSpacings-10-0 {
                padding-left: 5px !important;
                padding-right: 5px !important;
                padding-top: 0px !important;
                padding-bottom: 0px !important;
            }

            .pc-w620-padding-32-32-32-32 {
                padding: 32px 32px 32px 32px !important;
            }

            .pc-w620-gridCollapsed-1>tbody,
            .pc-w620-gridCollapsed-1>tbody>tr,
            .pc-w620-gridCollapsed-1>tr {
                display: inline-block !important;
            }

            .pc-w620-gridCollapsed-1.pc-width-fill>tbody,
            .pc-w620-gridCollapsed-1.pc-width-fill>tbody>tr,
            .pc-w620-gridCollapsed-1.pc-width-fill>tr {
                width: 100% !important;
            }

            .pc-w620-gridCollapsed-1.pc-w620-width-fill>tbody,
            .pc-w620-gridCollapsed-1.pc-w620-width-fill>tbody>tr,
            .pc-w620-gridCollapsed-1.pc-w620-width-fill>tr {
                width: 100% !important;
            }

            .pc-w620-gridCollapsed-1>tbody>tr>td,
            .pc-w620-gridCollapsed-1>tr>td {
                display: block !important;
                width: auto !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .pc-w620-gridCollapsed-1.pc-width-fill>tbody>tr>td,
            .pc-w620-gridCollapsed-1.pc-width-fill>tr>td {
                width: 100% !important;
            }

            .pc-w620-gridCollapsed-1.pc-w620-width-fill>tbody>tr>td,
            .pc-w620-gridCollapsed-1.pc-w620-width-fill>tr>td {
                width: 100% !important;
            }

            .pc-w620-gridCollapsed-1>tbody>.pc-grid-tr-first>.pc-grid-td-first,
            pc-w620-gridCollapsed-1>.pc-grid-tr-first>.pc-grid-td-first {
                padding-top: 0 !important;
            }

            .pc-w620-gridCollapsed-1>tbody>.pc-grid-tr-last>.pc-grid-td-last,
            pc-w620-gridCollapsed-1>.pc-grid-tr-last>.pc-grid-td-last {
                padding-bottom: 0 !important;
            }

            .pc-w620-gridCollapsed-0>tbody>.pc-grid-tr-first>td,
            .pc-w620-gridCollapsed-0>.pc-grid-tr-first>td {
                padding-top: 0 !important;
            }

            .pc-w620-gridCollapsed-0>tbody>.pc-grid-tr-last>td,
            .pc-w620-gridCollapsed-0>.pc-grid-tr-last>td {
                padding-bottom: 0 !important;
            }

            .pc-w620-gridCollapsed-0>tbody>tr>.pc-grid-td-first,
            .pc-w620-gridCollapsed-0>tr>.pc-grid-td-first {
                padding-left: 0 !important;
            }

            .pc-w620-gridCollapsed-0>tbody>tr>.pc-grid-td-last,
            .pc-w620-gridCollapsed-0>tr>.pc-grid-td-last {
                padding-right: 0 !important;
            }

            .pc-w620-tableCollapsed-1>tbody,
            .pc-w620-tableCollapsed-1>tbody>tr,
            .pc-w620-tableCollapsed-1>tr {
                display: block !important;
            }

            .pc-w620-tableCollapsed-1.pc-width-fill>tbody,
            .pc-w620-tableCollapsed-1.pc-width-fill>tbody>tr,
            .pc-w620-tableCollapsed-1.pc-width-fill>tr {
                width: 100% !important;
            }

            .pc-w620-tableCollapsed-1.pc-w620-width-fill>tbody,
            .pc-w620-tableCollapsed-1.pc-w620-width-fill>tbody>tr,
            .pc-w620-tableCollapsed-1.pc-w620-width-fill>tr {
                width: 100% !important;
            }

            .pc-w620-tableCollapsed-1>tbody>tr>td,
            .pc-w620-tableCollapsed-1>tr>td {
                display: block !important;
                width: auto !important;
            }

            .pc-w620-tableCollapsed-1.pc-width-fill>tbody>tr>td,
            .pc-w620-tableCollapsed-1.pc-width-fill>tr>td {
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .pc-w620-tableCollapsed-1.pc-w620-width-fill>tbody>tr>td,
            .pc-w620-tableCollapsed-1.pc-w620-width-fill>tr>td {
                width: 100% !important;
                box-sizing: border-box !important;
            }
        }

        @media (max-width: 520px) {
            .pc-w520-padding-25-25-25-25 {
                padding: 25px 25px 25px 25px !important;
            }

            .pc-w520-padding-10-30-10-30 {
                padding: 10px 30px 10px 30px !important;
            }
        }
    </style>
    <!--[if !mso]><!-- -->
    <style>
        @media all {
            @font-face {
                font-family: 'Montserrat';
                font-style: normal;
                font-weight: 600;
                src: url('https://fonts.gstatic.com/s/montserrat/v26/JTUHjIg1_i6t8kCHKm4532VJOt5-QNFgpCu173w3aXw.woff') format('woff'), url('https://fonts.gstatic.com/s/montserrat/v26/JTUHjIg1_i6t8kCHKm4532VJOt5-QNFgpCu173w3aXo.woff2') format('woff2');
            }

            @font-face {
                font-family: 'Montserrat';
                font-style: normal;
                font-weight: 700;
                src: url('https://fonts.gstatic.com/s/montserrat/v26/JTUHjIg1_i6t8kCHKm4532VJOt5-QNFgpCuM73w3aXw.woff') format('woff'), url('https://fonts.gstatic.com/s/montserrat/v26/JTUHjIg1_i6t8kCHKm4532VJOt5-QNFgpCuM73w3aXo.woff2') format('woff2');
            }

            @font-face {
                font-family: 'Poppins';
                font-style: normal;
                font-weight: 600;
                src: url('https://fonts.gstatic.com/s/poppins/v21/pxiByp8kv8JHgFVrLEj6Z1JlEw.woff') format('woff'), url('https://fonts.gstatic.com/s/poppins/v21/pxiByp8kv8JHgFVrLEj6Z1JlFQ.woff2') format('woff2');
            }

            @font-face {
                font-family: 'Poppins';
                font-style: normal;
                font-weight: 400;
                src: url('https://fonts.gstatic.com/s/poppins/v21/pxiEyp8kv8JHgFVrJJnedA.woff') format('woff'), url('https://fonts.gstatic.com/s/poppins/v21/pxiEyp8kv8JHgFVrJJnecg.woff2') format('woff2');
            }

            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 400;
                src: url('https://fonts.gstatic.com/s/firasans/v17/va9E4kDNxMZdWfMOD5VvmYjN.woff') format('woff'), url('https://fonts.gstatic.com/s/firasans/v17/va9E4kDNxMZdWfMOD5VvmYjL.woff2') format('woff2');
            }

            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 700;
                src: url('https://fonts.gstatic.com/s/firasans/v17/va9B4kDNxMZdWfMOD5VnLK3eSBf8.woff') format('woff'), url('https://fonts.gstatic.com/s/firasans/v17/va9B4kDNxMZdWfMOD5VnLK3eSBf6.woff2') format('woff2');
            }

            @font-face {
                font-family: 'Fira Sans';
                font-style: normal;
                font-weight: 500;
                src: url('https://fonts.gstatic.com/s/firasans/v17/va9B4kDNxMZdWfMOD5VnZKveSBf8.woff') format('woff'), url('https://fonts.gstatic.com/s/firasans/v17/va9B4kDNxMZdWfMOD5VnZKveSBf6.woff2') format('woff2');
            }
        }
    </style>
</head>

<body class="body pc-font-alt"
    style="width: 100% !important; min-height: 100% !important; margin: 0 !important; padding: 0 !important; line-height: 1.5; color: #2D3A41; mso-line-height-rule: exactly; -webkit-font-smoothing: antialiased; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; font-variant-ligatures: normal; text-rendering: optimizeLegibility; -moz-osx-font-smoothing: grayscale; background-color: #ffffff;"
    bgcolor="#ffffff">
    <table class="pc-project-body" style="table-layout: fixed; min-width: 600px; background-color: #ffffff;"
        bgcolor="#ffffff" width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation">
        <tr>
            <td align="center" valign="top">
                <table class="pc-project-container" align="center" width="600"
                    style="width: 600px; max-width: 600px;" border="0" cellpadding="0" cellspacing="0"
                    role="presentation">
                    <tr>
                        <td style="padding: 0px 0px 20px 0px;" align="left" valign="top">
                            <table border="0" cellpadding="0" cellspacing="0" role="presentation" width="100%"
                                style="width: 100%;">
                                <tr>
                                    <td valign="top">
                                        <!-- BEGIN MODULE: Header -->
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                            role="presentation">
                                            <tr>
                                                <td class="pc-w620-spacing-0-0-0-0" style="padding: 0px 0px 0px 0px;">
                                                    <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                                        role="presentation"
                                                        style="border-collapse: separate; border-spacing: 0px;">
                                                        <tr>
                                                            <!--[if !gte mso 9]><!-- -->
                                                            <td valign="top" class="pc-w620-padding-0-0-0-0"
                                                                style="background-size: cover; background-position: center; background-repeat: no-repeat; padding: 0px 0px 0px 0px; border-radius: 23px 23px 0px 0px; background-color: #f8f9fd;"
                                                                bgcolor="#f8f9fd"
                                                                background="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/gyt/bg.png">
                                                                <table width="100%" border="0" cellpadding="0"
                                                                    cellspacing="0" role="presentation">
                                                                    <tr>
                                                                        <td>
                                                                            <table
                                                                                class="pc-width-fill pc-w620-gridCollapsed-0"
                                                                                width="100%" border="0"
                                                                                cellpadding="0" cellspacing="0"
                                                                                role="presentation">
                                                                                <tr
                                                                                    class="pc-grid-tr-first pc-grid-tr-last">
                                                                                    <td class="pc-grid-td-first"
                                                                                        align="center" valign="top"
                                                                                        style="padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 0px;">
                                                                                        <table border="0"
                                                                                            cellpadding="0"
                                                                                            cellspacing="0"
                                                                                            role="presentation"
                                                                                            style="border-collapse: separate; border-spacing: 0;">
                                                                                            <tr>
                                                                                                <td align="left"
                                                                                                    valign="top">
                                                                                                    <table
                                                                                                        align="left"
                                                                                                        width="100%"
                                                                                                        border="0"
                                                                                                        cellpadding="0"
                                                                                                        cellspacing="0"
                                                                                                        role="presentation"
                                                                                                        style="width: 100%;">
                                                                                                    </table>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                    <td class="pc-grid-td-last"
                                                                                        align="center" valign="top"
                                                                                        style="padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 0px; mso-padding-left-alt: 0; margin-left: 0px;">
                                                                                        <table width="100%"
                                                                                            border="0"
                                                                                            cellpadding="0"
                                                                                            cellspacing="0"
                                                                                            role="presentation"
                                                                                            style="border-collapse: separate; border-spacing: 0; width: 100%;">
                                                                                            <tr>
                                                                                                <td class="pc-w620-padding-32-24-32-24"
                                                                                                    align="center"
                                                                                                    valign="middle"
                                                                                                    style="padding: 40px 32px 0px 32px; mso-padding-left-alt: 0; margin-left:32px;">
                                                                                                    <table
                                                                                                        align="center"
                                                                                                        width="100%"
                                                                                                        border="0"
                                                                                                        cellpadding="0"
                                                                                                        cellspacing="0"
                                                                                                        role="presentation"
                                                                                                        style="width: 100%;">
                                                                                                        <tr>
                                                                                                            <td align="center"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    width="100%"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td align="center"
                                                                                                                            valign="top"
                                                                                                                            style="padding: 0px 0px 20px 0px;">
                                                                                                                            <img src="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/gytlogo-1.png"
                                                                                                                                class="pc-w620-width-80 pc-w620-height-auto"
                                                                                                                                width="100"
                                                                                                                                height="104"
                                                                                                                                alt=""
                                                                                                                                style="display: block; outline: 0; line-height: 100%; -ms-interpolation-mode: bicubic; width:100px; height: auto; max-width: 100%; border: 0;" />
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                        <tr>
                                                                                                            <td align="center"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    align="center"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td class="pc-w620-spacing-0-0-16-0"
                                                                                                                            valign="top"
                                                                                                                            style="padding: 0px 0px 10px 0px;">
                                                                                                                            <table
                                                                                                                                border="0"
                                                                                                                                cellpadding="0"
                                                                                                                                cellspacing="0"
                                                                                                                                role="presentation"
                                                                                                                                width="100%"
                                                                                                                                style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                <tr>
                                                                                                                                    <td valign="top"
                                                                                                                                        class="pc-w620-padding-0-0-0-0"
                                                                                                                                        align="center"
                                                                                                                                        style="padding: 0px 40px 0px 40px; mso-padding-left-alt: 0; margin-left:40px;">
                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-32px pc-w620-lineHeight-120pc"
                                                                                                                                            style="line-height: 140%; letter-spacing: 0px; font-family: 'Montserrat', Arial, Helvetica, sans-serif; font-size: 25px; font-weight: bold; font-variant-ligatures: normal; color: #2d2d2f; text-transform: uppercase; text-align: center; text-align-last: center;">
                                                                                                                                            <div>
                                                                                                                                                <span
                                                                                                                                                    style="font-weight: 700;font-style: normal;color: rgb(18, 18, 18);">Hooray!
                                                                                                                                                    Your
                                                                                                                                                    order
                                                                                                                                                    has
                                                                                                                                                    been
                                                                                                                                                    confirmed.</span>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                    </td>
                                                                                                                                </tr>
                                                                                                                            </table>
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                        <tr>
                                                                                                            <td align="center"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    align="center"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td class="pc-w620-spacing-0-0-0-0"
                                                                                                                            valign="top"
                                                                                                                            style="padding: 0px 0px 10px 0px;">
                                                                                                                            <table
                                                                                                                                border="0"
                                                                                                                                cellpadding="0"
                                                                                                                                cellspacing="0"
                                                                                                                                role="presentation"
                                                                                                                                width="100%"
                                                                                                                                style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                <tr>
                                                                                                                                    <td valign="top"
                                                                                                                                        class="pc-w620-padding-0-0-0-0"
                                                                                                                                        align="center">
                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-14px pc-w620-lineHeight-140pc"
                                                                                                                                            style="line-height: 24px; letter-spacing: -0px; font-family: 'Poppins', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: normal; font-variant-ligatures: normal; color: #2d2d2f; text-align: center; text-align-last: center;">
                                                                                                                                            <div>
                                                                                                                                                <span
                                                                                                                                                    style="font-weight: 400;font-style: normal;color: rgba(18, 18, 18, 0.8);">View
                                                                                                                                                    tickets
                                                                                                                                                    in
                                                                                                                                                    your
                                                                                                                                                    profile
                                                                                                                                                    on
                                                                                                                                                    the
                                                                                                                                                    mobile
                                                                                                                                                    web.</span>
                                                                                                                                            </div>
                                                                                                                                            <div>
                                                                                                                                                <span>﻿</span>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                    </td>
                                                                                                                                </tr>
                                                                                                                            </table>
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                        <tr>
                                                                                                            <td align="center"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    width="100%"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td class="pc-w620-spacing-0-0-32-0"
                                                                                                                            style="padding: 0px 0px 48px 0px;">
                                                                                                                            <table
                                                                                                                                class="pc-width-fill pc-w620-gridCollapsed-1"
                                                                                                                                width="100%"
                                                                                                                                border="0"
                                                                                                                                cellpadding="0"
                                                                                                                                cellspacing="0"
                                                                                                                                role="presentation">
                                                                                                                                <tr
                                                                                                                                    class="pc-grid-tr-first pc-grid-tr-last">
                                                                                                                                    <td class="pc-grid-td-first pc-grid-td-last pc-w620-itemsSpacings-0-30"
                                                                                                                                        align="left"
                                                                                                                                        valign="top"
                                                                                                                                        style="width: 50%; padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 0px;">
                                                                                                                                        <table
                                                                                                                                            width="100%"
                                                                                                                                            border="0"
                                                                                                                                            cellpadding="0"
                                                                                                                                            cellspacing="0"
                                                                                                                                            role="presentation"
                                                                                                                                            style="border-collapse: separate; border-spacing: 0; width: 100%;">
                                                                                                                                            <tr>
                                                                                                                                                <td align="center"
                                                                                                                                                    valign="middle">
                                                                                                                                                    <table
                                                                                                                                                        align="center"
                                                                                                                                                        width="100%"
                                                                                                                                                        border="0"
                                                                                                                                                        cellpadding="0"
                                                                                                                                                        cellspacing="0"
                                                                                                                                                        role="presentation"
                                                                                                                                                        style="width: 100%;">
                                                                                                                                                        <tr>
                                                                                                                                                            <td align="center"
                                                                                                                                                                valign="top">
                                                                                                                                                                <table
                                                                                                                                                                    width="100%"
                                                                                                                                                                    border="0"
                                                                                                                                                                    cellpadding="0"
                                                                                                                                                                    cellspacing="0"
                                                                                                                                                                    role="presentation">
                                                                                                                                                                    <tr>
                                                                                                                                                                        <th valign="top"
                                                                                                                                                                            align="center"
                                                                                                                                                                            style="text-align: center; font-weight: normal; line-height: 1;">
                                                                                                                                                                            <a style="display: inline-block; box-sizing: border-box; border-radius: 296px 296px 296px 296px; background-color: #4a30cf; padding: 12px 72px 12px 72px; font-family: 'Poppins', Arial, Helvetica, sans-serif; font-weight: 600; font-size: 16px; line-height: 24px; letter-spacing: -0px; color: #ffffff; vertical-align: top; text-align: center; text-align-last: center; text-decoration: none; -webkit-text-size-adjust: none;"
                                                                                                                                                                                href="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/dashboard/bookings"
                                                                                                                                                                                target="_blank"><span
                                                                                                                                                                                    style="display: block;"><span>Download
                                                                                                                                                                                        now</span></span></a>
                                                                                                                                                                            <!--<![endif]-->
                                                                                                                                                                        </th>
                                                                                                                                                                    </tr>
                                                                                                                                                                </table>
                                                                                                                                                            </td>
                                                                                                                                                        </tr>
                                                                                                                                                        <tr>
                                                                                                                                                            <td align="center"
                                                                                                                                                                valign="top">
                                                                                                                                                                <table
                                                                                                                                                                    align="center"
                                                                                                                                                                    border="0"
                                                                                                                                                                    cellpadding="0"
                                                                                                                                                                    cellspacing="0"
                                                                                                                                                                    role="presentation">
                                                                                                                                                                    <tr>
                                                                                                                                                                        <td class="pc-w620-spacing-0-0-16-0"
                                                                                                                                                                            valign="top"
                                                                                                                                                                            style="padding: 0px 0px 10px 0px;">
                                                                                                                                                                            <table
                                                                                                                                                                                border="0"
                                                                                                                                                                                cellpadding="0"
                                                                                                                                                                                cellspacing="0"
                                                                                                                                                                                role="presentation"
                                                                                                                                                                                width="100%"
                                                                                                                                                                                style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                                <tr>
                                                                                                                                                                                    <td valign="top"
                                                                                                                                                                                        class="pc-w620-padding-0-0-0-0"
                                                                                                                                                                                        align="center"
                                                                                                                                                                                        style="padding: 0px 40px 0px 40px; mso-padding-left-alt: 0; margin-left:40px;">
                                                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-32px pc-w620-lineHeight-120pc"
                                                                                                                                                                                            style="line-height: 140%; letter-spacing: 0px; font-family: 'Montserrat', Arial, Helvetica, sans-serif; font-size: 20px; font-weight: bold; font-variant-ligatures: normal; color: #2d2d2f; text-transform: uppercase; text-align: center; text-align-last: center;">
                                                                                                                                                                                            <div>
                                                                                                                                                                                                <span>﻿</span>
                                                                                                                                                                                            </div>
                                                                                                                                                                                            <div>
                                                                                                                                                                                                <span
                                                                                                                                                                                                    style="font-weight: 700;font-style: normal;color: rgb(18, 18, 18);">EVENT
                                                                                                                                                                                                    details</span>
                                                                                                                                                                                            </div>
                                                                                                                                                                                            <div>
                                                                                                                                                                                                <span
                                                                                                                                                                                                    style="font-weight: 600;font-style: normal;color: rgb(18, 18, 18);">{{$body[0]['eventName']}}</span>
                                                                                                                                                                                            </div>
                                                                                                                                                                                        </div>
                                                                                                                                                                                    </td>
                                                                                                                                                                                </tr>
                                                                                                                                                                            </table>
                                                                                                                                                                        </td>
                                                                                                                                                                    </tr>
                                                                                                                                                                </table>
                                                                                                                                                            </td>
                                                                                                                                                        </tr>
                                                                                                                                                        <tr>
                                                                                                                                                            <td align="center"
                                                                                                                                                                valign="top">
                                                                                                                                                                <table
                                                                                                                                                                    border="0"
                                                                                                                                                                    cellpadding="0"
                                                                                                                                                                    cellspacing="0"
                                                                                                                                                                    role="presentation"
                                                                                                                                                                    align="center"
                                                                                                                                                                    style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                    <tr>
                                                                                                                                                                        <td valign="top"
                                                                                                                                                                            align="left">
                                                                                                                                                                            <div class="pc-font-alt"
                                                                                                                                                                                style="line-height: 21px; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 15px; font-weight: normal; font-variant-ligatures: normal; color: #333333; text-align: left; text-align-last: left;">
                                                                                                                                                                                <div>
                                                                                                                                                                                    <span
                                                                                                                                                                                        style="font-weight: 700;font-style: normal;">
                                                                                                                                                                                        🗺 : {{$body[0]['location']}}
                                                                                                                                                                                    </span>
                                                                                                                                                                                </div>
                                                                                                                                                                                <br/>
                                                                                                                                                                                <div>
                                                                                                                                                                                    <span
                                                                                                                                                                                        style="font-weight: 700;font-style: normal;">
                                                                                                                                                                                        📅 : {{$body[0]['DateTime']}}
                                                                                                                                                                                    </span>
                                                                                                                                                                                </div>
                                                                                                                                                                            </div>
                                                                                                                                                                        </td>
                                                                                                                                                                    </tr>
                                                                                                                                                                </table>
                                                                                                                                                            </td>
                                                                                                                                                        </tr>
                                                                                                                                                    </table>
                                                                                                                                                </td>
                                                                                                                                            </tr>
                                                                                                                                        </table>
                                                                                                                                    </td>
                                                                                                                                </tr>
                                                                                                                            </table>
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                        <tr>
                                                                                                            <td align="center"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    width="100%"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td>
                                                                                                                            <table
                                                                                                                                class="pc-w620-tableCollapsed-0"
                                                                                                                                border="0"
                                                                                                                                cellpadding="0"
                                                                                                                                cellspacing="0"
                                                                                                                                role="presentation"
                                                                                                                                width="100%"
                                                                                                                                style="border-collapse: separate; border-spacing: 0; width: 100%;">
                                                                                                                                <tbody>
                                                                                                                                    <tr>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation"
                                                                                                                                                width="100%"
                                                                                                                                                style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                <tr>
                                                                                                                                                    <td valign="top"
                                                                                                                                                        align="left">
                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-163pc"
                                                                                                                                                            style="line-height: 156%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: bold; font-variant-ligatures: normal; color: #151515; text-align: left; text-align-last: left;">
                                                                                                                                                            <div>
                                                                                                                                                                <span>Ticket
                                                                                                                                                                    Category</span>
                                                                                                                                                            </div>
                                                                                                                                                        </div>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation"
                                                                                                                                                width="100%"
                                                                                                                                                style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                <tr>
                                                                                                                                                    <td valign="top"
                                                                                                                                                        align="left">
                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-163pc"
                                                                                                                                                            style="line-height: 156%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: bold; font-variant-ligatures: normal; color: #151515; text-align: left; text-align-last: left;">
                                                                                                                                                            Qty
                                                                                                                                                        </div>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 0px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation"
                                                                                                                                                width="100%"
                                                                                                                                                style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                <tr>
                                                                                                                                                    <td valign="top"
                                                                                                                                                        align="left">
                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-163pc"
                                                                                                                                                            style="line-height: 156%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: bold; font-variant-ligatures: normal; color: #151515; text-align: left; text-align-last: left;">
                                                                                                                                                            Price
                                                                                                                                                        </div>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                    </tr>


                                                                                                                                        {{-- tickets loops start --}}
                                                                                                                                        @foreach ($body as $booking)

                                                                                                                                        <tr>
                                                                                                                                            <td align="left"
                                                                                                                                                valign="top"
                                                                                                                                                style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                                <table
                                                                                                                                                    width="100%"
                                                                                                                                                    border="0"
                                                                                                                                                    cellpadding="0"
                                                                                                                                                    cellspacing="0"
                                                                                                                                                    role="presentation">
                                                                                                                                                    <tr>
                                                                                                                                                        <td
                                                                                                                                                            align="left">
                                                                                                                                                            <table
                                                                                                                                                                class="pc-width-hug pc-w620-gridCollapsed-1"
                                                                                                                                                                align="left"
                                                                                                                                                                border="0"
                                                                                                                                                                cellpadding="0"
                                                                                                                                                                cellspacing="0"
                                                                                                                                                                role="presentation">
                                                                                                                                                                <tr
                                                                                                                                                                    class="pc-grid-tr-first pc-grid-tr-last">
                                                                                                                                                                    <td class="pc-grid-td-last pc-w620-itemsSpacings-0-10"
                                                                                                                                                                        valign="top"
                                                                                                                                                                        style="padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 10px; mso-padding-left-alt: 0; margin-left: 10px;">
                                                                                                                                                                        <table
                                                                                                                                                                            border="0"
                                                                                                                                                                            cellpadding="0"
                                                                                                                                                                            cellspacing="0"
                                                                                                                                                                            role="presentation"
                                                                                                                                                                            style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                            <tr>
                                                                                                                                                                                <td align="left"
                                                                                                                                                                                    valign="top">
                                                                                                                                                                                    <table
                                                                                                                                                                                        align="left"
                                                                                                                                                                                        width="100%"
                                                                                                                                                                                        border="0"
                                                                                                                                                                                        cellpadding="0"
                                                                                                                                                                                        cellspacing="0"
                                                                                                                                                                                        role="presentation"
                                                                                                                                                                                        style="width: 100%;">
                                                                                                                                                                                        <tr>
                                                                                                                                                                                            <td align="left"
                                                                                                                                                                                                valign="top">
                                                                                                                                                                                                <table
                                                                                                                                                                                                    align="left"
                                                                                                                                                                                                    border="0"
                                                                                                                                                                                                    cellpadding="0"
                                                                                                                                                                                                    cellspacing="0"
                                                                                                                                                                                                    role="presentation">
                                                                                                                                                                                                    <tr>
                                                                                                                                                                                                        <td valign="top"
                                                                                                                                                                                                            style="padding: 0px 0px 4px 0px;">
                                                                                                                                                                                                            <table
                                                                                                                                                                                                                border="0"
                                                                                                                                                                                                                cellpadding="0"
                                                                                                                                                                                                                cellspacing="0"
                                                                                                                                                                                                                role="presentation"
                                                                                                                                                                                                                width="100%"
                                                                                                                                                                                                                style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                                                                <tr>
                                                                                                                                                                                                                    <td valign="top"
                                                                                                                                                                                                                        align="left"
                                                                                                                                                                                                                        style="padding: 9px 0px 0px 0px;">
                                                                                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-163pc"
                                                                                                                                                                                                                            style="line-height: 156%; letter-spacing: -0.3px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: 500; font-variant-ligatures: normal; color: #151515; text-align: left; text-align-last: left;">
                                                                                                                                                                                                                            {{$booking['category']}}
                                                                                                                                                                                                                        </div>
                                                                                                                                                                                                                    </td>
                                                                                                                                                                                                                </tr>
                                                                                                                                                                                                            </table>
                                                                                                                                                                                                        </td>
                                                                                                                                                                                                    </tr>
                                                                                                                                                                                                </table>
                                                                                                                                                                                            </td>
                                                                                                                                                                                        </tr>
                                                                                                                                                                                    </table>
                                                                                                                                                                                </td>
                                                                                                                                                                            </tr>
                                                                                                                                                                        </table>
                                                                                                                                                                    </td>
                                                                                                                                                                </tr>
                                                                                                                                                            </table>
                                                                                                                                                        </td>
                                                                                                                                                    </tr>
                                                                                                                                                </table>
                                                                                                                                            </td>
                                                                                                                                            <td align="left"
                                                                                                                                                valign="top"
                                                                                                                                                style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                                <table
                                                                                                                                                    border="0"
                                                                                                                                                    cellpadding="0"
                                                                                                                                                    cellspacing="0"
                                                                                                                                                    role="presentation"
                                                                                                                                                    width="100%"
                                                                                                                                                    style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                    <tr>
                                                                                                                                                        <td valign="top"
                                                                                                                                                            align="right">
                                                                                                                                                            <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-125pc"
                                                                                                                                                                style="line-height: 122%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: normal; font-variant-ligatures: normal; color: #9b9b9b; text-align: right; text-align-last: right;">
                                                                                                                                                                {{$booking['qty']}}
                                                                                                                                                            </div>
                                                                                                                                                        </td>
                                                                                                                                                    </tr>
                                                                                                                                                </table>
                                                                                                                                            </td>
                                                                                                                                            <td align="left"
                                                                                                                                                valign="top"
                                                                                                                                                style="padding: 20px 0px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                                <table
                                                                                                                                                    border="0"
                                                                                                                                                    cellpadding="0"
                                                                                                                                                    cellspacing="0"
                                                                                                                                                    role="presentation"
                                                                                                                                                    width="100%"
                                                                                                                                                    style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                    <tr>
                                                                                                                                                        <td valign="top"
                                                                                                                                                            align="right">
                                                                                                                                                            <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-125pc"
                                                                                                                                                                style="line-height: 122%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: normal; font-variant-ligatures: normal; color: #151515; text-align: right; text-align-last: right;">
                                                                                                                                                                ₹{{$booking['price']}}
                                                                                                                                                            </div>
                                                                                                                                                        </td>
                                                                                                                                                    </tr>
                                                                                                                                                </table>
                                                                                                                                            </td>
                                                                                                                                        </tr>
                                                                                                                                    @endforeach
                                                                                                                                                {{-- ticket loop end --}}

                                                                                                                                    {{-- total summary --}}
                                                                                                                                    <tr>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                width="100%"
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation">
                                                                                                                                                <tr>
                                                                                                                                                    <td
                                                                                                                                                        align="left">
                                                                                                                                                        <table
                                                                                                                                                            class="pc-width-hug pc-w620-gridCollapsed-1"
                                                                                                                                                            align="left"
                                                                                                                                                            border="0"
                                                                                                                                                            cellpadding="0"
                                                                                                                                                            cellspacing="0"
                                                                                                                                                            role="presentation">
                                                                                                                                                            <tr
                                                                                                                                                                class="pc-grid-tr-first pc-grid-tr-last">
                                                                                                                                                                <td class="pc-grid-td-last pc-w620-itemsSpacings-0-10"
                                                                                                                                                                    valign="top"
                                                                                                                                                                    style="padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 10px; mso-padding-left-alt: 0; margin-left: 10px;">
                                                                                                                                                                    <table
                                                                                                                                                                        border="0"
                                                                                                                                                                        cellpadding="0"
                                                                                                                                                                        cellspacing="0"
                                                                                                                                                                        role="presentation"
                                                                                                                                                                        style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                        <tr>
                                                                                                                                                                            <td align="left"
                                                                                                                                                                                valign="top">
                                                                                                                                                                                <table
                                                                                                                                                                                    align="left"
                                                                                                                                                                                    width="100%"
                                                                                                                                                                                    border="0"
                                                                                                                                                                                    cellpadding="0"
                                                                                                                                                                                    cellspacing="0"
                                                                                                                                                                                    role="presentation"
                                                                                                                                                                                    style="width: 100%;">
                                                                                                                                                                                    <tr>
                                                                                                                                                                                        <td align="left"
                                                                                                                                                                                            valign="top">
                                                                                                                                                                                            <table
                                                                                                                                                                                                align="left"
                                                                                                                                                                                                border="0"
                                                                                                                                                                                                cellpadding="0"
                                                                                                                                                                                                cellspacing="0"
                                                                                                                                                                                                role="presentation">
                                                                                                                                                                                                <tr>
                                                                                                                                                                                                    <td valign="top"
                                                                                                                                                                                                        style="padding: 0px 0px 4px 0px;">
                                                                                                                                                                                                        <table
                                                                                                                                                                                                            border="0"
                                                                                                                                                                                                            cellpadding="0"
                                                                                                                                                                                                            cellspacing="0"
                                                                                                                                                                                                            role="presentation"
                                                                                                                                                                                                            width="100%"
                                                                                                                                                                                                            style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                                                            <tr>
                                                                                                                                                                                                                <td valign="top"
                                                                                                                                                                                                                    align="left"
                                                                                                                                                                                                                    style="padding: 9px 0px 0px 0px;">
                                                                                                                                                                                                                    <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-163pc"
                                                                                                                                                                                                                        style="line-height: 156%; letter-spacing: -0.3px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: 500; font-variant-ligatures: normal; color: #151515; text-align: left; text-align-last: left;">

                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                </td>
                                                                                                                                                                                                            </tr>
                                                                                                                                                                                                        </table>
                                                                                                                                                                                                    </td>
                                                                                                                                                                                                </tr>
                                                                                                                                                                                            </table>
                                                                                                                                                                                        </td>
                                                                                                                                                                                    </tr>
                                                                                                                                                                                </table>
                                                                                                                                                                            </td>
                                                                                                                                                                        </tr>
                                                                                                                                                                    </table>
                                                                                                                                                                </td>
                                                                                                                                                            </tr>
                                                                                                                                                        </table>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation"
                                                                                                                                                width="100%"
                                                                                                                                                style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                <tr>
                                                                                                                                                    <td valign="top" align="right">
                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-125pc"
                                                                                                                                                            style="line-height: 122%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: normal; font-variant-ligatures: normal; color: #9b9b9b; text-align: right; text-align-last: right;">
                                                                                                                                                            <strong>Total Tax</strong>
                                                                                                                                                        </div>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 0px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation"
                                                                                                                                                width="100%"
                                                                                                                                                style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                <tr>
                                                                                                                                                    <td valign="top"
                                                                                                                                                        align="right">
                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-125pc"
                                                                                                                                                            style="line-height: 122%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: normal; font-variant-ligatures: normal; color: #151515; text-align: right; text-align-last: right;">
                                                                                                                                                            ₹{{$body[0]['convenience_fee']}}
                                                                                                                                                        </div>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                    </tr>
                                                                                                                                    <tr>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                width="100%"
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation">
                                                                                                                                                <tr>
                                                                                                                                                    <td
                                                                                                                                                        align="left">
                                                                                                                                                        <table
                                                                                                                                                            class="pc-width-hug pc-w620-gridCollapsed-1"
                                                                                                                                                            align="left"
                                                                                                                                                            border="0"
                                                                                                                                                            cellpadding="0"
                                                                                                                                                            cellspacing="0"
                                                                                                                                                            role="presentation">
                                                                                                                                                            <tr
                                                                                                                                                                class="pc-grid-tr-first pc-grid-tr-last">
                                                                                                                                                                <td class="pc-grid-td-last pc-w620-itemsSpacings-0-10"
                                                                                                                                                                    valign="top"
                                                                                                                                                                    style="padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 10px; mso-padding-left-alt: 0; margin-left: 10px;">
                                                                                                                                                                    <table
                                                                                                                                                                        border="0"
                                                                                                                                                                        cellpadding="0"
                                                                                                                                                                        cellspacing="0"
                                                                                                                                                                        role="presentation"
                                                                                                                                                                        style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                        <tr>
                                                                                                                                                                            <td align="left"
                                                                                                                                                                                valign="top">
                                                                                                                                                                                <table
                                                                                                                                                                                    align="left"
                                                                                                                                                                                    width="100%"
                                                                                                                                                                                    border="0"
                                                                                                                                                                                    cellpadding="0"
                                                                                                                                                                                    cellspacing="0"
                                                                                                                                                                                    role="presentation"
                                                                                                                                                                                    style="width: 100%;">
                                                                                                                                                                                    <tr>
                                                                                                                                                                                        <td align="left"
                                                                                                                                                                                            valign="top">
                                                                                                                                                                                            <table
                                                                                                                                                                                                align="left"
                                                                                                                                                                                                border="0"
                                                                                                                                                                                                cellpadding="0"
                                                                                                                                                                                                cellspacing="0"
                                                                                                                                                                                                role="presentation">
                                                                                                                                                                                                <tr>
                                                                                                                                                                                                    <td valign="top"
                                                                                                                                                                                                        style="padding: 0px 0px 4px 0px;">
                                                                                                                                                                                                        <table
                                                                                                                                                                                                            border="0"
                                                                                                                                                                                                            cellpadding="0"
                                                                                                                                                                                                            cellspacing="0"
                                                                                                                                                                                                            role="presentation"
                                                                                                                                                                                                            width="100%"
                                                                                                                                                                                                            style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                                                                                            <tr>
                                                                                                                                                                                                                <td valign="top"
                                                                                                                                                                                                                    align="left"
                                                                                                                                                                                                                    style="padding: 9px 0px 0px 0px;">
                                                                                                                                                                                                                    <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-163pc"
                                                                                                                                                                                                                        style="line-height: 156%; letter-spacing: -0.3px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: 500; font-variant-ligatures: normal; color: #151515; text-align: left; text-align-last: left;">

                                                                                                                                                                                                                    </div>
                                                                                                                                                                                                                </td>
                                                                                                                                                                                                            </tr>
                                                                                                                                                                                                        </table>
                                                                                                                                                                                                    </td>
                                                                                                                                                                                                </tr>
                                                                                                                                                                                            </table>
                                                                                                                                                                                        </td>
                                                                                                                                                                                    </tr>
                                                                                                                                                                                </table>
                                                                                                                                                                            </td>
                                                                                                                                                                        </tr>
                                                                                                                                                                    </table>
                                                                                                                                                                </td>
                                                                                                                                                            </tr>
                                                                                                                                                        </table>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 10px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation"
                                                                                                                                                width="100%"
                                                                                                                                                style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                <tr>
                                                                                                                                                    <td valign="top"
                                                                                                                                                        align="right">
                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-125pc"
                                                                                                                                                            style="line-height: 122%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: normal; font-variant-ligatures: normal; color: #9b9b9b; text-align: right; text-align-last: right;">
                                                                                                                                                            <strong>Booking Total</strong>
                                                                                                                                                        </div>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                        <td align="left"
                                                                                                                                            valign="top"
                                                                                                                                            style="padding: 20px 0px 20px 0px; border-bottom: 1px solid #E5E5E5;">
                                                                                                                                            <table
                                                                                                                                                border="0"
                                                                                                                                                cellpadding="0"
                                                                                                                                                cellspacing="0"
                                                                                                                                                role="presentation"
                                                                                                                                                width="100%"
                                                                                                                                                style="border-collapse: separate; border-spacing: 0; margin-right: auto; margin-left: auto;">
                                                                                                                                                <tr>
                                                                                                                                                    <td valign="top"
                                                                                                                                                        align="right">
                                                                                                                                                        <div class="pc-font-alt pc-w620-fontSize-16 pc-w620-lineHeight-125pc"
                                                                                                                                                            style="line-height: 122%; letter-spacing: -0.2px; font-family: 'Fira Sans', Arial, Helvetica, sans-serif; font-size: 18px; font-weight: normal; font-variant-ligatures: normal; color: #151515; text-align: right; text-align-last: right;">
                                                                                                                                                            ₹{{$body[0]['total']}}
                                                                                                                                                        </div>
                                                                                                                                                    </td>
                                                                                                                                                </tr>
                                                                                                                                            </table>
                                                                                                                                        </td>
                                                                                                                                    </tr>
                                                                                                                                </tbody>
                                                                                                                            </table>
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                    </table>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- END MODULE: Header -->
                                    </td>
                                </tr>
                                <tr>
                                    <td valign="top">
                                        <!-- BEGIN MODULE: Footer -->
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                            role="presentation">
                                            <tr>
                                                <td class="pc-w620-spacing-0-0-0-0" style="padding: 0px 0px 0px 0px;">
                                                    <table width="100%" border="0" cellspacing="0"
                                                        cellpadding="0" role="presentation"
                                                        style="border-collapse: separate; border-spacing: 0px;">
                                                        <tr>
                                                            <td valign="top" class="pc-w620-padding-32-32-32-32"
                                                                style="background-size: cover; background-position: center; background-repeat: no-repeat; padding: 0px 24px 32px 24px; border-radius: 0px 0px 24px 24px; background-color: #f8f9fd;"
                                                                bgcolor="#f8f9fd"
                                                                background="images/image-172223948876412.png">

                                                                <table width="100%" border="0" cellpadding="0"
                                                                    cellspacing="0" role="presentation"
                                                                    style="width: 100%;">
                                                                    <tr>
                                                                        <td class="pc-w620-spacing-0-0-32-0"
                                                                            valign="top"
                                                                            style="padding: 0px 20px 32px 20px;mso-padding-left-alt: 0; margin-left:20px;">
                                                                            <table class="pc-w620-width-100pc"
                                                                                width="100%" border="0"
                                                                                cellpadding="0" cellspacing="0"
                                                                                role="presentation">
                                                                                <tr>
                                                                                    <td height="1" valign="top"
                                                                                        style="line-height: 1px; font-size: 1px; border-bottom: 1px solid #d6d6d652;">
                                                                                        &nbsp;</td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                                <table width="100%" border="0" cellpadding="0"
                                                                    cellspacing="0" role="presentation">
                                                                    <tr>
                                                                        <td class="pc-w620-spacing-0-0-32-0 pc-w620-valign-middle pc-w620-halign-center"
                                                                            style="padding: 0px 20px 32px 20px; mso-padding-left-alt: 0; margin-left:20px;">
                                                                            <table
                                                                                class="pc-width-fill pc-w620-gridCollapsed-1 pc-w620-halign-center"
                                                                                width="100%" border="0"
                                                                                cellpadding="0" cellspacing="0"
                                                                                role="presentation">
                                                                                <tr
                                                                                    class="pc-grid-tr-first pc-grid-tr-last">
                                                                                    <td class="pc-grid-td-first pc-w620-itemsSpacings-0-24"
                                                                                        align="left" valign="middle"
                                                                                        style="width: 50%; padding-top: 0px; padding-right: 20px; padding-bottom: 0px; padding-left: 0px;">
                                                                                        <table
                                                                                            class="pc-w620-width-fill pc-w620-halign-center"
                                                                                            border="0"
                                                                                            cellpadding="0"
                                                                                            cellspacing="0"
                                                                                            role="presentation"
                                                                                            style="border-collapse: separate; border-spacing: 0;">
                                                                                            <tr>
                                                                                                <td class="pc-w620-halign-center pc-w620-valign-middle"
                                                                                                    align="left"
                                                                                                    valign="middle">
                                                                                                    <table
                                                                                                        class="pc-w620-halign-center"
                                                                                                        align="left"
                                                                                                        width="100%"
                                                                                                        border="0"
                                                                                                        cellpadding="0"
                                                                                                        cellspacing="0"
                                                                                                        role="presentation"
                                                                                                        style="width: 100%;">
                                                                                                        <tr>
                                                                                                            <td class="pc-w620-halign-center"
                                                                                                                align="left"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    width="100%"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td class="pc-w620-halign-center"
                                                                                                                            align="left"
                                                                                                                            valign="top">
                                                                                                                            <img src="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/gyt/gyt2.png"
                                                                                                                                class="pc-w620-align-center"
                                                                                                                                width="150"
                                                                                                                                height="18"
                                                                                                                                alt=""
                                                                                                                                style="display: block; outline: 0; line-height: 100%; -ms-interpolation-mode: bicubic; width:150px; height: auto; max-width: 100%; border: 0;" />
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                    </table>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                    <td class="pc-grid-td-last pc-w620-itemsSpacings-0-24"
                                                                                        align="left" valign="middle"
                                                                                        style="width: 50%; padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 20px; mso-padding-left-alt: 0; margin-left: 20px;">
                                                                                        <table
                                                                                            class="pc-w620-width-hug"
                                                                                            width="100%"
                                                                                            border="0"
                                                                                            cellpadding="0"
                                                                                            cellspacing="0"
                                                                                            role="presentation"
                                                                                            style="border-collapse: separate; border-spacing: 0; width: 100%;">
                                                                                            <tr>
                                                                                                <td class="pc-w620-halign-center pc-w620-valign-middle"
                                                                                                    align="right"
                                                                                                    valign="middle">
                                                                                                    <table
                                                                                                        class="pc-w620-halign-center"
                                                                                                        align="right"
                                                                                                        width="100%"
                                                                                                        border="0"
                                                                                                        cellpadding="0"
                                                                                                        cellspacing="0"
                                                                                                        role="presentation"
                                                                                                        style="width: 100%;">
                                                                                                        <tr>
                                                                                                            <td class="pc-w620-halign-center"
                                                                                                                align="right"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    class="pc-w620-halign-center"
                                                                                                                    align="right"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td class="pc-w620-valign-middle pc-w620-halign-center"
                                                                                                                            align="right">
                                                                                                                            <table
                                                                                                                                class="pc-width-hug pc-w620-gridCollapsed-0 pc-w620-width-hug pc-w620-halign-center"
                                                                                                                                align="right"
                                                                                                                                border="0"
                                                                                                                                cellpadding="0"
                                                                                                                                cellspacing="0"
                                                                                                                                role="presentation">
                                                                                                                                <tr class="pc-grid-tr-first pc-grid-tr-last">
                                                                                                                                    <td class="pc-grid-td-first pc-w620-itemsSpacings-40-0" valign="middle" style="padding-top: 0px; padding-right: 10px; padding-bottom: 0px; padding-left: 0px;">
                                                                                                                                        <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                         <tbody><tr>
                                                                                                                                          <td class="pc-w620-halign-center pc-w620-valign-middle" align="right" valign="middle">
                                                                                                                                           <table class="pc-w620-halign-center" align="right" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%;">
                                                                                                                                            <tbody><tr>
                                                                                                                                             <td class="pc-w620-halign-center" align="right" valign="top">
                                                                                                                                              <table class="pc-w620-halign-center" align="right" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                                                                                                               <tbody><tr>
                                                                                                                                                <td valign="top">
                                                                                                                                                 <img src="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/gyt/fb.png" class="" width="18" height="18" style="display: block; border: 0; outline: 0; line-height: 100%; -ms-interpolation-mode: bicubic; width:18px; height:18px;" alt="">
                                                                                                                                                </td>
                                                                                                                                               </tr>
                                                                                                                                              </tbody></table>
                                                                                                                                             </td>
                                                                                                                                            </tr>
                                                                                                                                           </tbody></table>
                                                                                                                                          </td>
                                                                                                                                         </tr>
                                                                                                                                        </tbody></table>
                                                                                                                                       </td>
                                                                                                                                       <td class="pc-w620-itemsSpacings-40-0" valign="middle" style="padding-top: 0px; padding-right: 10px; padding-bottom: 0px; padding-left: 10px; mso-padding-left-alt: 0; margin-left: 10px;">
                                                                                                                                        <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                         <tbody><tr>
                                                                                                                                          <td class="pc-w620-halign-center pc-w620-valign-middle" align="right" valign="middle">
                                                                                                                                           <table class="pc-w620-halign-center" align="right" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%;">
                                                                                                                                            <tbody><tr>
                                                                                                                                             <td class="pc-w620-halign-center" align="right" valign="top">
                                                                                                                                              <table class="pc-w620-halign-center" align="right" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                                                                                                               <tbody><tr>
                                                                                                                                                <td valign="top">
                                                                                                                                                 <img src="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/gyt/x.png" class="" width="18" height="18" style="display: block; border: 0; outline: 0; line-height: 100%; -ms-interpolation-mode: bicubic; width:18px; height:18px;" alt="">
                                                                                                                                                </td>
                                                                                                                                               </tr>
                                                                                                                                              </tbody></table>
                                                                                                                                             </td>
                                                                                                                                            </tr>
                                                                                                                                           </tbody></table>
                                                                                                                                          </td>
                                                                                                                                         </tr>
                                                                                                                                        </tbody></table>
                                                                                                                                       </td>
                                                                                                                                       <td class="pc-w620-itemsSpacings-40-0" valign="middle" style="padding-top: 0px; padding-right: 10px; padding-bottom: 0px; padding-left: 10px; mso-padding-left-alt: 0; margin-left: 10px;">
                                                                                                                                        <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                         <tbody><tr>
                                                                                                                                          <td class="pc-w620-halign-center pc-w620-valign-middle" align="right" valign="middle">
                                                                                                                                           <table class="pc-w620-halign-center" align="right" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%;">
                                                                                                                                            <tbody><tr>
                                                                                                                                             <td class="pc-w620-halign-center" align="right" valign="top">
                                                                                                                                              <table class="pc-w620-halign-center" align="right" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                                                                                                               <tbody><tr>
                                                                                                                                                <td valign="top">
                                                                                                                                                 <img src="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/gyt/ig.png" class="" width="18" height="18" style="display: block; border: 0; outline: 0; line-height: 100%; -ms-interpolation-mode: bicubic; width:18px; height:18px;" alt="">
                                                                                                                                                </td>
                                                                                                                                               </tr>
                                                                                                                                              </tbody></table>
                                                                                                                                             </td>
                                                                                                                                            </tr>
                                                                                                                                           </tbody></table>
                                                                                                                                          </td>
                                                                                                                                         </tr>
                                                                                                                                        </tbody></table>
                                                                                                                                       </td>
                                                                                                                                       <td class="pc-grid-td-last pc-w620-itemsSpacings-40-0" valign="middle" style="padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 10px; mso-padding-left-alt: 0; margin-left: 10px;">
                                                                                                                                        <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                         <tbody><tr>
                                                                                                                                          <td class="pc-w620-halign-center pc-w620-valign-middle" align="right" valign="middle">
                                                                                                                                           <table class="pc-w620-halign-center" align="right" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%;">
                                                                                                                                            <tbody><tr>
                                                                                                                                             <td class="pc-w620-halign-center" align="right" valign="top">
                                                                                                                                              <table class="pc-w620-halign-center" align="right" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                                                                                                                               <tbody><tr>
                                                                                                                                                <td valign="top">
                                                                                                                                                 <img src="{{ rtrim(env('ALLOWED_DOMAIN', 'https://ssgarba.com'), '/') }}/gyt/yt.png" class="" width="18" height="18" style="display: block; border: 0; outline: 0; line-height: 100%; -ms-interpolation-mode: bicubic; width:18px; height:18px;" alt="">
                                                                                                                                                </td>
                                                                                                                                               </tr>
                                                                                                                                              </tbody></table>
                                                                                                                                             </td>
                                                                                                                                            </tr>
                                                                                                                                           </tbody></table>
                                                                                                                                          </td>
                                                                                                                                         </tr>
                                                                                                                                        </tbody></table>
                                                                                                                                       </td>
                                                                                                                                </tr>
                                                                                                                            </table>
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                    </table>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                                <table width="100%" border="0" cellpadding="0"
                                                                    cellspacing="0" role="presentation">
                                                                    <tr>
                                                                        <td align="center">
                                                                            <table
                                                                                class="pc-width-hug pc-w620-gridCollapsed-0"
                                                                                align="center" border="0"
                                                                                cellpadding="0" cellspacing="0"
                                                                                role="presentation">
                                                                                <tr
                                                                                    class="pc-grid-tr-first pc-grid-tr-last">
                                                                                    <td class="pc-grid-td-first pc-grid-td-last pc-w620-itemsSpacings-10-0"
                                                                                        valign="top"
                                                                                        style="padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 0px;">
                                                                                        <table border="0"
                                                                                            cellpadding="0"
                                                                                            cellspacing="0"
                                                                                            role="presentation"
                                                                                            style="border-collapse: separate; border-spacing: 0;">
                                                                                            <tr>
                                                                                                <td align="center"
                                                                                                    valign="top">
                                                                                                    <table
                                                                                                        align="center"
                                                                                                        width="100%"
                                                                                                        border="0"
                                                                                                        cellpadding="0"
                                                                                                        cellspacing="0"
                                                                                                        role="presentation"
                                                                                                        style="width: 100%;">
                                                                                                        <tr>
                                                                                                            <td align="center"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    align="center"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td valign="top"
                                                                                                                            style="padding: 0px 0px 32px 0px;">
                                                                                                                            <table
                                                                                                                                border="0"
                                                                                                                                cellpadding="0"
                                                                                                                                cellspacing="0"
                                                                                                                                role="presentation"
                                                                                                                                width="100%"
                                                                                                                                style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                <tr>
                                                                                                                                    <td valign="top"
                                                                                                                                        align="center">
                                                                                                                                        <div class="pc-font-alt"
                                                                                                                                            style="line-height: 140%; letter-spacing: -0.2px; font-family: 'Poppins', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: normal; font-variant-ligatures: normal; color: #2d2d2f; text-align: center; text-align-last: center;">
                                                                                                                                            <div>
                                                                                                                                                <span></span>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                    </td>
                                                                                                                                </tr>
                                                                                                                            </table>
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                        <tr>
                                                                                                            <td align="center"
                                                                                                                valign="top">
                                                                                                                <table
                                                                                                                    width="100%"
                                                                                                                    align="center"
                                                                                                                    border="0"
                                                                                                                    cellpadding="0"
                                                                                                                    cellspacing="0"
                                                                                                                    role="presentation">
                                                                                                                    <tr>
                                                                                                                        <td
                                                                                                                            valign="top">
                                                                                                                            <table
                                                                                                                                border="0"
                                                                                                                                cellpadding="0"
                                                                                                                                cellspacing="0"
                                                                                                                                role="presentation"
                                                                                                                                width="100%"
                                                                                                                                align="center"
                                                                                                                                style="border-collapse: separate; border-spacing: 0;">
                                                                                                                                <tr>
                                                                                                                                    <td valign="top"
                                                                                                                                        align="center">
                                                                                                                                        <div class="pc-font-alt"
                                                                                                                                            style="line-height: 140%; letter-spacing: -0.3px; font-family: 'Poppins', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 600; font-variant-ligatures: normal; color: #2d2d2f; text-align: center; text-align-last: center;">
                                                                                                                                            <div>
                                                                                                                                                <span>www.ssgarba.com</span>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                    </td>
                                                                                                                                </tr>
                                                                                                                            </table>
                                                                                                                        </td>
                                                                                                                    </tr>
                                                                                                                </table>
                                                                                                            </td>
                                                                                                        </tr>
                                                                                                    </table>
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <!-- END MODULE: Footer -->
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table width="100%" border="0" cellpadding="0" cellspacing="0"
                                            role="presentation">
                                            <tr>
                                                <td align="center" valign="top"
                                                    style="padding-top: 20px; padding-bottom: 20px; vertical-align: top;">
                                                    <a href="https://designmodo.com/postcards?uid=MjU4MDEx&type=footer"
                                                        target="_blank"
                                                        style="text-decoration: none; overflow: hidden; border-radius: 2px; display: inline-block;">
                                                        <img src="images/promo-footer-dark.jpg" width="198"
                                                            height="46" alt="Made with (o -) postcards"
                                                            style="width: 198px; height: auto; margin: 0 auto; border: 0; outline: 0; line-height: 100%; -ms-interpolation-mode: bicubic; vertical-align: top;">
                                                    </a>
                                                    <img src="https://api-postcards.designmodo.com/tracking/mail/promo?uid=MjU4MDEx"
                                                        width="1" height="1" alt=""
                                                        style="display:none; width: 1px; height: 1px;">
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <!-- Fix for Gmail on iOS -->
    <div class="pc-gmail-fix" style="white-space: nowrap; font: 15px courier; line-height: 0;">&nbsp; &nbsp; &nbsp;
        &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
        &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
    </div>
</body>

</html>
