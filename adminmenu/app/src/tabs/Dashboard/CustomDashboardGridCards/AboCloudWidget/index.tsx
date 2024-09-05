import React from "react";
import DashboardGridCard from "@webstollen/react-jtl-plugin/lib/components/DashboardPage/DashboardGridCard";
import {usePluginInfo} from "@webstollen/react-jtl-plugin/lib";
import abocloudImg from '../../../../assets/img/abocloud.png';

const MollieDashboardLink = () => {
    const pluginInfo = usePluginInfo();
    const prefix = pluginInfo.endpoint.substring(0, pluginInfo.endpoint.lastIndexOf('/')) + '/app/build';

    return (
        <DashboardGridCard style={{padding: "0px"}}>
            <div className="w-full h-full relative">{/* This div only exists to prevent shadow from affecting other DashboardGridCards due to position:relative */}
                <a href={'https://ws-url.de/abocloud-mollie'} target={"_blank"} rel={"noreferrer"}>
                    <img style={{borderRadius: "15px", maxHeight: "100%", minWidth: "100%"}} src={prefix + abocloudImg} title={'abocloud'} alt={'abocloud'}/>
                </a>
            </div>
        </DashboardGridCard>
    )
}

export default MollieDashboardLink;


