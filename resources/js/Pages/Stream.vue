<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';
import Peer from "simple-peer";
import { ref } from 'vue';
const page = usePage();

const authUser = page.props.auth.user;
const streamId = page.props.streamId;
const videoStream = ref(null);
const videoStream2 = ref(null);
const broadcasterId = ref(null);
const broadcasterPeer = ref(null);

const joinBroadcast = () => {
    initializeStreamingChannel();
    initializeSignalOfferChannel(); // a private channel where the viewer listens to incoming signalling offer
};
const initializeStreamingChannel = () => {
    Echo.join(`streaming-channel.${streamId}`);
};
const createViewerPeer = async (incomingOffer, broadcaster) => {
    const stream = await getPermissions();
    videoStream2.value.srcObject = stream;

    const peer = new Peer({
        initiator: false,
        trickle: false,
        stream: stream,
        config: {
            iceServers: [
                {
                    urls: ["stun:stun.stunprotocol.org", "stun:stun1.l.google.com:19302"],
                },
                // {
                //     urls: [process.env.TURN_SERVER_HOST],
                //     username: process.env.TURN_SERVER_USER,
                //     credential: process.env.TURN_SERVER_CRED,
                // },
            ],
        },
    });
    // Add Transceivers
    peer.addTransceiver("video", { direction: "recvonly" });
    peer.addTransceiver("audio", { direction: "recvonly" });
    // Initialize Peer events for connection to remote peer
    handlePeerEvents(
        peer,
        incomingOffer,
        broadcaster,
        removeBroadcastVideo
    );
    broadcasterPeer.value = peer;
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
        videoStream.value.srcObject = stream;
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

    Echo.private(`stream-signal-channel.${authUser.id}`)
        .listen("StreamOffer", ({ data }) => {
            console.log("Signal Offer from private channel");
            broadcasterId.value = data.broadcaster;
            createViewerPeer(data.offer, data.broadcaster);
        });
};
const removeBroadcastVideo = () => {
    console.log("removingBroadcast Video");
    alert("Livestream ended by broadcaster");
    const tracks = videoStream.value.srcObject.getTracks();
    tracks.forEach((track) => {
        track.stop();
    });
    videoStream.value.srcObject = null;
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
            <button @click="joinBroadcast" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Join
            </button>
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    <video autoplay ref="videoStream"></video>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
