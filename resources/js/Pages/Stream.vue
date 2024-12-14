<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import Peer from "simple-peer";
const page = usePage()

Echo.private(`App.Models.User.${page.props.auth.user.id}`)
    .listen('OrderShipmentStatusUpdated', (e) => {
        console.log(e.order);
    });
Echo.channel(`notifications.${page.props.auth.user.id}`)
    .notification((data) => {
        console.log(data);
    });

const joinBroadcast = () => {
    this.initializeStreamingChannel();
    this.initializeSignalOfferChannel(); // a private channel where the viewer listens to incoming signalling offer
};
const initializeStreamingChannel = () => {
    this.streamingPresenceChannel = window.Echo.join(
    `streaming-channel.${this.stream_id}`
    );
};
const createViewerPeer = (incomingOffer, broadcaster) => {
    const peer = new Peer({
    initiator: false,
    trickle: false,
    config: {
        iceServers: [
        {
            urls: "stun:stun.stunprotocol.org",
        },
        {
            urls: this.turn_url,
            username: this.turn_username,
            credential: this.turn_credential,
        },
        ],
    },
    });
    // Add Transceivers
    peer.addTransceiver("video", { direction: "recvonly" });
    peer.addTransceiver("audio", { direction: "recvonly" });
    // Initialize Peer events for connection to remote peer
    this.handlePeerEvents(
    peer,
    incomingOffer,
    broadcaster,
    this.removeBroadcastVideo
    );
    this.broadcasterPeer = peer;
};
const handlePeerEvents =(peer, incomingOffer, broadcaster, cleanupCallback) => {
    peer.on("signal", (data) => {
    axios
        .post("/stream-answer", {
        broadcaster,
        answer: data,
        })
        .then((res) => {
        console.log(res);
        })
        .catch((err) => {
        console.log(err);
        });
    });
    peer.on("stream", (stream) => {
    // display remote stream
    this.$refs.viewer.srcObject = stream;
    });
    peer.on("track", (track, stream) => {
    console.log("onTrack");
    });
    peer.on("connect", () => {
    console.log("Viewer Peer connected");
    });
    peer.on("close", () => {
    console.log("Viewer Peer closed");
    peer.destroy();
    cleanupCallback();
    });
    peer.on("error", (err) => {
    console.log("handle error gracefully");
    });
    const updatedOffer = {
    ...incomingOffer,
    sdp: `${incomingOffer.sdp}\n`,
    };
    peer.signal(updatedOffer);
};
const initializeSignalOfferChannel = () => {
    window.Echo.private(`stream-signal-channel.${this.auth_user_id}`).listen(
    "StreamOffer",
    ({ data }) => {
        console.log("Signal Offer from private channel");
        this.broadcasterId = data.broadcaster;
        this.createViewerPeer(data.offer, data.broadcaster);
    }
    );
};
const removeBroadcastVideo = () => {
    console.log("removingBroadcast Video");
    alert("Livestream ended by broadcaster");
    const tracks = this.$refs.viewer.srcObject.getTracks();
    tracks.forEach((track) => {
    track.stop();
    });
    this.$refs.viewer.srcObject = null;
};

</script>

<template>
    <AppLayout title="Stream">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Stream
            </h2>
        </template>

        <div class="py-12">
            <button>Join</button>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <video ref="stream"></video>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
